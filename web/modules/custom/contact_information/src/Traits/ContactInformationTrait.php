<?php

declare(strict_types=1);

namespace Drupal\contact_information\Traits;

use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Reusable methods for contact information.
 */
trait ContactInformationTrait {

  /**
   * {@inheritdoc}
   */
  public function getValidatedContactInformationEmails(): array {
    $contacts = $this->getValidatedContactInformationEntities();
    return array_map(function (ContactInformationInterface $contact): string {
      return $contact->get('ci_email')->value;
    }, $contacts);
  }

  /**
   * {@inheritdoc}
   */
  public function getValidatedContactInformationEntities(): array {
    assert(is_subclass_of($this, GroupInterface::class), __TRAIT__ . ' is intended to be used in bundle classes for group entities.');
    assert(method_exists($this, 'getReferencedEntityIds'), __TRAIT__ . ' depends on JoinupBundleClassFieldAccessTrait. Please include it in your class.');
    $ids = $this->getReferencedEntityIds($this->getContactInformationFieldName())['node'] ?? [];
    if (empty($ids)) {
      return $ids;
    }
    $contacts = $this->entityTypeManager()->getStorage('node')->loadMultiple($ids);
    /** @var \Drupal\email_confirmer\EmailConfirmerManagerInterface $emailConfirmer */
    $emailConfirmer = \Drupal::getContainer()->get('email_confirmer');
    return array_filter($contacts, function (ContactInformationInterface $contact) use ($emailConfirmer): bool {
      return (bool) $emailConfirmer->getConfirmation($contact->get('ci_email')->value, 'confirmed', ContactInformationEmailConfirmInterface::REALM);
    });
  }

}
