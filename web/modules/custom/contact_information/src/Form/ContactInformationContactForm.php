<?php

declare(strict_types=1);

namespace Drupal\contact_information\Form;

use Drupal\contact_information\Entity\ContactInformation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Default class to build the Contact Form.
 */
class ContactInformationContactForm extends ContactInformationFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RdfInterface $rdf_entity = NULL): array {
    $form = parent::buildForm($form, $form_state);

    // We cannot use the current url in case we open modal.
    $entity = $this->entityTypeManager->getStorage($this->entityType)
      ->load($this->entityId);
    if (!empty($this->fieldSettings['modal']) && $entity instanceof ContactInformation) {
      $settings = $form_state->get('settings');
      $settings['default_message'] = '[current-user:name] sent a message using the contact form at ' .
        $entity->getRelatedGroup()->toUrl()->setAbsolute()->toString() . '.';
      $form_state->set('settings', $settings);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmailAddresses(): array {
    return email_contact_get_emails_from_field($this->entityType, $this->entityId, $this->fieldName);
  }

}
