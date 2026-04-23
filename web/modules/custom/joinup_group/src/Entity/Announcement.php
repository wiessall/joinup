<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Url;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\message\Entity\Message;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;

/**
 * Bundle class for announcement messages.
 */
class Announcement extends Message implements AnnouncementInterface {

  use EntityWorkflowStateTrait;
  use GroupContentTrait;

  /**
   * {@inheritdoc}
   */
  public function setSubject(string $subject): AnnouncementInterface {
    $this->set('subject', $subject);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject(): ?string {
    return $this->get('subject')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setBody(string $body): AnnouncementInterface {
    $this->set('body', $body);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getBody(): ?string {
    return $this->get('body')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getBodyFormat(): string {
    $template = $this->getTemplate();
    $text = $template->get('text');
    if (!isset($text[0])) {
      throw new \RuntimeException("The 'announcement' message template should have a single text item");
    }
    return $text[0]['format'];
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'state';
  }

  /**
   * {@inheritdoc}
   */
  public function getText($langcode = NULL, $delta = NULL): array {
    $text = parent::getText($langcode, $delta);

    // Replace the text with the processed announcement body.
    if (isset($text[0])) {
      $build = [
        '#type' => 'processed_text',
        '#text' => $this->getBody(),
        '#format' => $this->getBodyFormat(),
      ];
      $body = \Drupal::service('renderer')->renderInIsolation($build);
      $text[0] = $this->processArguments($this->getArguments(), [$body])[0];
    }

    return $text;
  }

  /**
   * Returns the group reference field name.
   *
   * @return string
   *   The group reference field name.
   */
  protected function getGroupFieldName(): string {
    return 'group';
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipients(int $offset, int $limit): array {
    $uids = $this->getRecipientsQuery()
      ->range($offset, $limit)
      ->execute()
      ->fetchCol();
    return array_map('intval', $uids);
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipientsCount(): int {
    return (int) $this->getRecipientsQuery()
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Provides a helper function preparing a query.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The database query.
   */
  protected function getRecipientsQuery(): SelectInterface {
    $group = $this->getGroup();

    $query = \Drupal::database()->select('og_membership', 'og');
    $query->join('users_field_data', 'u', 'og.uid = u.uid');
    // Only users which accepted the 'Legal notice' are receiving announcements.
    $query->join('entity_legal_document_acceptance', 'a', 'og.uid = a.uid');

    if ($professionalDomainIds = $this->getProfessionalDomainIds()) {
      $query->join('user__field_user_professional_domain', 'pd', 'u.uid = pd.entity_id');
      $query->condition('pd.field_user_professional_domain_target_id', $professionalDomainIds, 'IN');
    }

    return $query->condition('og.entity_type', 'node')
      ->fields('u', ['uid'])
      ->distinct()
      ->condition('og.entity_bundle', $group->bundle())
      ->condition('og.entity_id', $group->id())
      ->condition('og.state', OgMembershipInterface::STATE_ACTIVE)
      ->condition('og.announcements', 1)
      ->condition('u.status', 1)
      ->orderBy('u.uid');
  }

  /**
   * {@inheritdoc}
   */
  public function setProfessionalDomains(array $ids): self {
    $this->set('professional_domain', $ids);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfessionalDomainIds(): array {
    return array_map(
      fn(array $value) => $value['target_id'],
      $this->get('professional_domain')->getValue(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMailVariables(): array {
    $arguments = $this->getArguments();

    // Resolve callback-style args using the same logic as ::processArguments().
    $variables = [];
    foreach ($arguments as $key => $value) {
      if (is_array($value) && !empty($value['callback']) && is_callable($value['callback'])) {
        $value += ['pass message' => FALSE, 'arguments' => []];
        if ($value['pass message']) {
          $value['arguments']['message'] = $this;
        }
        $value = call_user_func_array($value['callback'], $value['arguments']);
      }

      $key = ltrim($key, '@%!');
      $variables[$key] = $value;
    }

    return $variables;
  }

  /**
   * {@inheritdoc}
   */
  public function addUserArguments(UserInterface $user): self {
    $this->setArguments(array_merge($this->getArguments(), [
      '@recipient_name' => $user->getDisplayName(),
      '@recipient_profile' => $user->toUrl()->setAbsolute()->toString(),
      '@recipient_profile_edit' => $user->toUrl('edit-form')->setAbsolute()->toString(),
      '@recipient_subscriptions' => Url::fromRoute('joinup_subscription.subscriptions', ['user' => $user->id()])->setAbsolute()->toString(),
      '@recipient_unsubscribe' => Url::fromRoute('joinup_subscription.unsubscribe', [
        'user' => $user->id(),
        'group' => $this->getGroup()->id(),
        'types' => 'announcements',
      ])->setAbsolute()->toString(),
    ]));
    return $this;
  }

}
