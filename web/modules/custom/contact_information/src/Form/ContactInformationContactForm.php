<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\email_contact\Form\ContactForm;

/**
 * Default class to build the Contact Form.
 */
class ContactInformationContactForm extends ContactForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#maxlength' => 255,
    ];
    return $form;
  }

  /**
   * Prepares and sends the message.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function sendMessage(array &$form, FormStateInterface $form_state): void {
    $emails = unserialize($form_state->getValue('emails'));
    // E-mail address of the sender: as the form field is a text field,
    // all instances of \r and \n have been automatically stripped from it.
    $reply_to = $form_state->getValue('mail');
    $settings = $form_state->get('settings');

    $params['subject'] = $form_state->getValue('subject');
    $params['name'] = $form_state->getValue('name');
    $params['organization'] = $form_state->getValue('organization');
    $params['default_message'] = $settings['default_message'];
    $message = '';

    if ($settings['include_values']) {
      $message .= 'Name: ' . $params['name'] . '<br/>' . 'Email: ' . $reply_to . '<br/>' . '<br />Organization: ' .$params['organization'];
    }
    $message .= '<br/>Message: ' . $form_state->getValue('message');
    $params['message'] = Markup::create($message);

    // Send the e-mail to the recipients.
    $mailManager = \Drupal::service('plugin.manager.mail');
    $to = implode(', ', $emails);
    $module = 'email_contact';
    $key = 'contact';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;

    $result = $mailManager->mail($module, $key, $to, $langcode, $params, $reply_to, $send);
    if ($result['result'] !== TRUE) {
      $this->messenger()->addError($this->t('There was a problem sending your message and it was not sent.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Your message has been sent.'));
      $msg = 'Email sent from: @replyto to: @to about: "@subject" containing: "@message"';
      $this->logger('email_contact')->notice($msg, [
        '@name' => $params['name'],
        '@replyto' => $reply_to,
        '@to' => $to,
        '@subject' => $params['subject'],
        '@message' => $params['message']
      ]);
    }
  }

}
