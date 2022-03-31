<?php

declare(strict_types = 1);

namespace Drupal\contact_form\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contact Form.
 */
class ContactForm extends FormBase {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a \Drupal\contact_form\ContactForm object.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(MailManagerInterface $mail_manager) {
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('plugin.manager.mail'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'contact_form_mail';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $entity_type = NULL, ?string $entity_id = NULL, ?string $field_name = NULL): array {
    $form['#prefix'] = '<div id="modal_contact_form_mail">';
    $form['#suffix'] = '</div>';

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    $user = $this->currentUser();
    $emails = contact_form_get_emails_from_field($entity_type, $entity_id, $field_name);
    $form['emails'] = [
      '#type' => 'value',
      '#value' => serialize($emails),
    ];
    $form['name'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Your name'),
      '#maxlength' => 255,
      '#default_value' => $user->id() ? $user->getDisplayName() : '',
      '#required' => FALSE,
    ];
    $form['mail'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Your e-mail address'),
      '#maxlength' => 255,
      '#default_value' => $user->id() ? $user->getEmail() : '',
      '#required' => FALSE,
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
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (preg_match("/\r|\n/", $form_state->getValue('subject'))) {
      $form_state->setErrorByName('subject', $this->t('The subject cannot contain linebreaks.'));
      $msg = 'Email injection exploit attempted in email form subject: @subject';
      $this->logger('email_contact')->notice($msg, ['@subject' => $form_state['values']['subject']]);
    }
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_contact_form_mail', $form));
    }
    else {
      $emails = unserialize($form_state->getValue('emails'), ['allowed_classes' => FALSE]);
      // E-mail address of the sender: as the form field is a text field,
      // all instances of \r and \n have been automatically stripped from it.
      $reply_to = $form_state->getValue('mail');
      $settings = $form_state->get('settings');

      $params['subject'] = $form_state->getValue('subject');
      $params['name'] = $form_state->getValue('name');
      $params['default_message'] = $settings['default_message'];
      $message = '';

      if ($settings['include_values']) {
        $message .= "Name: {$params['name']}<br/>Email: {$reply_to}<br/>";
      }
      $message .= '<br/>Message: ' . $form_state->getValue('message');
      $params['message'] = Markup::create($message);

      // Send the e-mail to the recipients.
      $to = implode(', ', $emails);
      $module = 'contact_form';
      $key = 'contact';
      $langcode = $this->currentUser()->getPreferredLangcode();
      $send = TRUE;
      $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, $reply_to, $send);
      if ($result['result'] !== TRUE) {
        $this->messenger()->addError($this->t('There was a problem sending your message and it was not sent.'));
        $response->addCommand(new ReplaceCommand('#contact_form_mail', $form));
      }
      else {
        $response->addCommand(new MessageCommand('Your message has been sent.'));
        $msg = 'Email sent from: @replyto to: @to about: "@subject" containing: "@message"';
        $this->logger('email_contact')->notice($msg, [
          '@name' => $params['name'],
          '@replyto' => $reply_to,
          '@to' => $to,
          '@subject' => $params['subject'],
          '@message' => $params['message'],
        ]);
        $response->addCommand(new CloseDialogCommand());
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
