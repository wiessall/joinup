<?php

declare(strict_types=1);

namespace Drupal\contact_information;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\email_confirmer\EmailConfirmerManagerInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\sparql_entity_storage\SparqlGraphInterface;

/**
 * Default implementation for 'contact_information.email_confirm' service.
 */
class ContactInformationEmailConfirm implements ContactInformationEmailConfirmInterface {

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\email_confirmer\EmailConfirmerManagerInterface $emailConfirmer
   *   The email confirmer service.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7300
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected RendererInterface $renderer, protected EmailConfirmerManagerInterface $emailConfirmer) {}

  /**
   * {@inheritdoc}
   */
  public function confirmEmailsForGroup(GroupInterface $group): array {
    $processed_emails = $confirmed_emails = [];

    // Only act on published groups.
    if (!$group->isPublished()) {
      return $confirmed_emails;
    }

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $field */
    $field = $group->get($group->getContactInformationFieldName());
    /** @var \Drupal\contact_information\Entity\ContactInformationInterface $contact_information */
    foreach ($field->referencedEntities() as $contact_information) {
      $email_field = $contact_information->get('ci_email');
      if (!$email_field->isEmpty()) {
        $email = $email_field->value;
        if (!in_array($email, $processed_emails, TRUE)) {
          if (!$this->emailConfirmer->getConfirmation($email, FALSE, static::REALM)) {
            $confirmation = $this->emailConfirmer->confirm($email, [
              'groups' => [$group->id()],
            ], static::REALM);
            if ($confirmation) {
              $confirmed_emails[] = $email;
            }
          }
          // Different contact information entities might have the same email.
          // We register them in $emails array to prevent duplication.
          $processed_emails[] = $email;
        }
      }
    }
    return $confirmed_emails;
  }

  /**
   * {@inheritdoc}
   */
  public function confirmEmail(string $email): bool {
    if (!$this->emailConfirmer->getConfirmation($email, FALSE, static::REALM)) {
      // Ignore if the contact information is not attached to any group.
      if ($gids = $this->getGroupsUsingContactInfoWithEmail($email)) {
        return (bool) $this->emailConfirmer->confirm($email, ['groups' => $gids], static::REALM);
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupsUsingContactInfoWithEmail(string $email): array {
    $storage = $this->entityTypeManager->getStorage('rdf_entity');
    $contact_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('rid', 'contact_information')
      ->condition('ci_email', $email)
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

  /**
   * {@inheritdoc}
   */
  public function deleteContactInfoConfirmation(ContactInformationInterface $contact_information): void {
    $email_field = $contact_information->get('ci_email');
    if (!$email_field->isEmpty()) {
      $email = $email_field->value;
      $email_is_used_elsewhere = (bool) $this->entityTypeManager->getStorage('rdf_entity')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('rid', 'contact_information')
        ->condition('ci_email', $email)
        ->condition('id', $contact_information->id(), '<>')
        ->count()
        ->execute();
      if (!$email_is_used_elsewhere) {
        if ($confirmation = $this->emailConfirmer->getConfirmation($email, FALSE, static::REALM)) {
          $confirmation->delete();
        }
      }
    }
  }

}
