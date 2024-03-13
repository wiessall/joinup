<?php

declare(strict_types=1);

namespace Drupal\contact_information\Form;

/**
 * Default class to build the Contact Form.
 */
class ContactInformationContactForm extends ContactInformationFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEmailAddresses(): array {
    return email_contact_get_emails_from_field($this->entityType, $this->entityId, $this->fieldName);
  }

}
