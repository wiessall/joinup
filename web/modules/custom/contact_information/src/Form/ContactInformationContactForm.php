<?php

declare(strict_types=1);

namespace Drupal\contact_information\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\contact_information\Entity\ContactInformation;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Default class to build the Contact Form.
 */
class ContactInformationContactForm extends ContactInformationFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RdfInterface $rdf_entity = NULL): array|AjaxResponse {
    $form = parent::buildForm($form, $form_state);

    // We cannot use the current url in case we open modal.
    $entity = $this->entityTypeManager->getStorage($this->entityType)
      ->load($this->entityId);

    if ($entity instanceof ContactInformation) {
      $group = $entity->getRelatedGroup();
      if (!$group && $this->accessIsAllowed()) {
        return $this->logAndBuildContactInfoInaccessibilityResponse($entity);
      }

      $settings = $form_state->get('settings');
      $settings['default_message'] = '[current-user:name] sent a message using the contact form at ' .
        $group?->toUrl()->setAbsolute()->toString() . '.';
      $form_state->set('settings', $settings);
    }

    return $form;
  }

  /**
   * Returns response if contact information cannot receive messages anymore.
   *
   * This method logs a warning message to the given logger channel and triggers
   * an error message being rendered on the current page.
   *
   * @param \Drupal\contact_information\Entity\ContactInformationInterface $contact
   *   The contact information entity.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response command if we have AJAX context, an empty array
   *   otherwise.
   */
  public function logAndBuildContactInfoInaccessibilityResponse(
    ContactInformationInterface $contact,
  ): array|AjaxResponse {
    $request = $this->getRequest();
    $this->logger('contact_information')->warning(
      "Attempting to send message to contact '%contact-id' which does not belong to a group.",
      array_filter([
        '%contact-id' => $contact?->id(),
        '%url' => $request->getRequestUri(),
        '%referer' => $request->headers->get('referer'),
      ])
    );

    $errorMessage = $this->t(
      'No message can be sent to %contact anymore.',
      ['%contact' => $contact->getName()]
    );

    if ($this->isAjax()) {
      return (new AjaxResponse())
        ->addCommand(new MessageCommand(
          message: $errorMessage,
          options: ['type' => 'error'],
        ));
    }

    $this->messenger()->addError($errorMessage);
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmailAddresses(): array {
    return email_contact_get_emails_from_field($this->entityType, $this->entityId, $this->fieldName);
  }

}
