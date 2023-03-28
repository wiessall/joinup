<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Controller;

use Drupal\contact_information\Form\ContactInformationContactForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\email_contact\Controller\ContactController;

/**
 * Controller for the contact_information_contact module.
 */
class ContactInformationContactController extends ContactController {

  /**
   * {@inheritDoc}
   */
  protected function getForm(EntityInterface $entity, string $field_name, array $field_settings): array {
    return $this->formBuilder->getForm(new ContactInformationContactForm(
      $entity->getEntityTypeId(),
      $entity->id(),
      $field_name,
      $field_settings
    ));
  }

}
