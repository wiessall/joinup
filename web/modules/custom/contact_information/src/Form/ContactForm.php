<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\email_contact\Form\ContactForm as OriginalContactForm;
use Drupal\email_contact\Plugin\Field\FieldFormatter\EmailContactLinkFormatter;
use Drupal\rdf_entity\RdfInterface;

/**
 * Extend email contact form.
 */
class ContactForm extends OriginalContactForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RdfInterface $rdf_entity = NULL): array {
    $user = $this->currentUser();
    $form['emails'] = [
      '#type' => 'value',
      '#value' => serialize($rdf_entity->getValidatedContactInformation()),
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name'),
      '#maxlength' => 255,
      '#default_value' => $user->id() ? $user->getDisplayName() : '',
      '#required' => TRUE,
    ];

    $form['mail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your e-mail address'),
      '#maxlength' => 255,
      '#default_value' => $user->id() ? $user->getEmail() : '',
      '#required' => TRUE,
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
    ];

    if (!$form_state->get('settings')) {
      $settings = EmailContactLinkFormatter::defaultSettings();
      $settings['default_message'] = '[current-user:name] sent a message using the contact form at [current-page:url].';
      $form_state->set('settings', $settings);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send e-mail'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->sendMessage($form, $form_state);
  }

  /**
   * Access check for the form.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RdfInterface $rdf_entity): AccessResultInterface {
    return AccessResult::allowedIf($this->currentUser()->isAuthenticated() && !empty($rdf_entity->getValidatedContactInformation()));
  }

}
