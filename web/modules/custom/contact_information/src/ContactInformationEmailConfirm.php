<?php

declare(strict_types = 1);

namespace Drupal\contact_information;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\email_confirmer\EmailConfirmerManagerInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\sparql_entity_storage\SparqlGraphInterface;

/**
 * Default implementation for 'contact_information.email_confirm' service.
 */
class ContactInformationEmailConfirm implements ContactInformationEmailConfirmInterface {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * The email confirmer service.
   */
  protected EmailConfirmerManagerInterface $emailConfirmer;

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\email_confirmer\EmailConfirmerManagerInterface|null $email_confirmer
   *   The email confirmer service. The $email_confirmer argument is set as
   *   nullable to avoid the non-existent service "email_confirmer" error during
   *   the deployment. This should be reverted after deploying this change.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7300
   * @todo Make $email_confirmer non-nullable in ISAICP-7300.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, ?EmailConfirmerManagerInterface $email_confirmer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    // @todo Remove this in ISAICP-7300.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7300
    // @codingStandardsIgnoreStart
    if (!$email_confirmer) {
      $email_confirmer = \Drupal::service('email_confirmer');
    }
    // @codingStandardsIgnoreEnd
    $this->emailConfirmer = $email_confirmer;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmEmailsForGroup(GroupInterface $group): void {
    // Only act on published groups.
    if (!$group->isPublished()) {
      return;
    }

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field */
    $field = $group->get($group->getContactInformationFieldName());
    $emails = [];
    /** @var \Drupal\contact_information\Entity\ContactInformationInterface $contact_information */
    foreach ($field->referencedEntities() as $contact_information) {
      $email_field = $contact_information->get('field_ci_email');
      if (!$email_field->isEmpty()) {
        $email = $email_field->value;
        if (!in_array($email, $emails, TRUE)) {
          if (!$this->emailConfirmer->getConfirmation($email, FALSE, static::REALM)) {
            $this->emailConfirmer->confirm($email, [
              'groups' => [$group->id()],
            ], static::REALM);
          }
          // Different contact information entities might have the same email. We
          // register them in $emails array to prevent duplication.
          $emails[] = $email;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function confirmEmail(string $email): void {
    // Ignore if the contact information is not attached to any group.
    if ($gids = $this->getGroupsUsingContactInfoWithEmail($email)) {
      $this->emailConfirmer->confirm($email, ['groups' => $gids], static::REALM);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupsUsingContactInfoWithEmail(string $email): array {
    $storage = $this->entityTypeManager->getStorage('rdf_entity');
    $contact_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('rid', 'contact_information')
      ->condition('field_ci_email', $email)
      ->execute();

    if (!$contact_ids) {
      // No contact information entity with this email.
      return [];
    }

    $query = $storage->getQuery();
    $or = $query->orConditionGroup()
      ->condition('field_ar_contact_information', $contact_ids, 'IN')
      ->condition('field_is_contact_information', $contact_ids, 'IN');

    return array_values($query
      ->accessCheck(FALSE)
      // Allow only published groups.
      ->graphs([SparqlGraphInterface::DEFAULT])
      ->condition('rid', array_values(JoinupGroupHelper::GROUP_BUNDLES), 'IN')
      ->condition($or)
      // Collections go first.
      ->sort('rid')
      ->sort('label')
      ->execute());
  }

}
