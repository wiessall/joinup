<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Traits;

use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Reusable methods for contact information.
 */
trait ContactInformationTrait {

  /**
   * Returns validated emails from collection/solution.
   */
  public function getValidatedContactInformation(): array {
    assert(is_subclass_of($this, GroupInterface::class), __TRAIT__ . ' is intended to be used in bundle classes for group entities.');
    assert(method_exists($this, 'getReferencedEntityIds'), __TRAIT__ . ' depends on JoinupBundleClassFieldAccessTrait. Please include it in your class.');
    $ids = $this->getReferencedEntityIds($this->getContactInformationFieldName())['rdf_entity'] ?? [];
    if (empty($ids)) {
      return $ids;
    }
    $contacts = $this->entityTypeManager()->getStorage('rdf_entity')->loadMultiple($ids);
    $emails = [];
    /** @var \Drupal\email_confirmer\EmailConfirmerManagerInterface $email_confirmer */
    $email_confirmer = \Drupal::service('email_confirmer');
    foreach ($contacts as $contact) {
      $confirmation = $email_confirmer->getConfirmation($contact->get('field_ci_email')->value);
      if ($confirmation && $confirmation->isConfirmed()) {
        $emails[] = $contact->get('field_ci_email')->value;
      }
    }
    return $emails;
  }

}
