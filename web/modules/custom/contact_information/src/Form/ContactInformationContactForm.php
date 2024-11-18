<?php

declare(strict_types=1);

namespace Drupal\contact_information\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\contact_information\Entity\ContactInformation;
use Drupal\contact_information\Traits\ContactInfoInaccessibleTrait;

/**
 * Default class to build the Contact Form.
 */
class ContactInformationContactForm extends ContactInformationFormBase {

  use ContactInfoInaccessibleTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array|AjaxResponse {
    $form = parent::buildForm($form, $form_state);

    // We cannot use the current url in case we open modal.
    $entity = $this->entityTypeManager->getStorage($this->entityType)
      ->load($this->entityId);

    if ($entity instanceof ContactInformation) {
      $group = $entity->getRelatedGroup();
      if (!$group && $this->accessIsAllowed()) {
        return static::logAndBuildContactInfoInaccessibilityResponse(
          $this->getRequest(),
          $this->logger(static::LOGGER_CHANNEL),
          $entity,
          $this->isAjax()
        );
      }

      $settings = $form_state->get('settings');
      $settings['default_message'] = '[current-user:name] sent a message using the contact form at ' .
        $group?->toUrl()->setAbsolute()->toString() . '.';
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
