<?php

declare(strict_types=1);

namespace Drupal\contact_information\Form;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\email_contact\Form\ContactForm;
use Drupal\file\FileInterface;
use Drupal\honeypot\HoneypotServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for the contact information form.
 */
abstract class ContactInformationFormBase extends ContactForm {

  /**
   * Constructs a new contact information form base.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   Mail manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\honeypot\HoneypotServiceInterface|null $honeypot
   *   Honeypot service.
   * @param string|null $entityType
   *   Entity type.
   * @param string|null $entityId
   *   Entity id.
   * @param string|null $fieldName
   *   Field name.
   * @param array|null $fieldSettings
   *   Field settings.
   */
  public function __construct(
    protected MailManagerInterface $mailManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ?HoneypotServiceInterface $honeypot,
    ?string $entityType = NULL,
    ?string $entityId = NULL,
    ?string $fieldName = NULL,
    ?array $fieldSettings = NULL,
  ) {
    parent::__construct($entityType, $entityId, $fieldName, $fieldSettings);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if ($this->currentUser()->isAnonymous()) {
      $form = $this->buildFormForAnonymous($form);
    }
    else {
      $form = $this->buildFormForAuthenticated($form, $form_state);
    }

    if (!empty($this->fieldSettings['modal'])) {
      $form['actions']['cancel'] = [
        '#type' => 'button',
        '#value' => $this->t('Cancel'),
        '#attributes' => ['class' => ['dialog-cancel']],
        // Put the cancel button to the left of the confirmation button, so it
        // is consistent with the dialog shown when joining the group.
        '#weight' => -1,
      ];

      $form['actions']['submit']['#attributes']['class'][] = 'use-ajax';
      $form['actions']['submit']['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $form['#prefix'] = '<div id="email_contact_modal_form">';
      $form['#suffix'] = '</div>';
    }

    // Vary the form's cache by whether the user is logged in or not.
    CacheableMetadata::createFromRenderArray($form)
      ->setCacheContexts(['user.roles:authenticated'])
      ->applyTo($form);

    return $form;
  }

  /**
   * Build form for anonymous user.
   *
   * For anonymous users, we change the form to enforce them to login.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The form structure.
   */
  protected function buildFormForAnonymous(array $form): array {
    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Only signed in users can send a message. Please sign in or register an account on EU Login.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Sign in / Register'),
        '#button_type' => 'primary',
        '#ajax' => [
          'callback' => 'contact_information_require_login',
          'event' => 'click',
        ],
      ],
    ];

    return $form;
  }

  /**
   * Build form for authenticated user.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  protected function buildFormForAuthenticated(array $form, FormStateInterface $form_state): array {
    $user = $this->currentUser();

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name'),
      '#maxlength' => 255,
      '#default_value' => $user->id() ? $user->getDisplayName() : '',
      '#required' => TRUE,
      '#weight' => 1,
    ];

    $form['organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#maxlength' => 255,
      '#weight' => 2,
    ];

    $form['mail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your e-mail address'),
      '#maxlength' => 255,
      '#default_value' => $user->id() ? $user->getEmail() : '',
      '#required' => TRUE,
      '#weight' => 3,
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of support'),
      '#options' => $this->getTypeOfSupportOptions(),
      '#required' => TRUE,
      '#weight' => 4,
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#weight' => 5,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#required' => TRUE,
      '#weight' => 6,
    ];

    $extensions = 'azw css csv dbf doc docx dtd.sgml dtd.xml e00 eps epub fmx2 fmx3 fmx4 gif gml gzip indd jpeg jpg json kml kmz mdb mobi mop mxd odf ods odt op.datpro owl pdf pdfa1a pdfa1b pdfx png pps ppsx ppt pptx ps psd rdf rdf.turtle rdf.xml rss rtf schema.xml sgml skos.xml sparqlq sparqlqres tar tar.gz tar.xz tiff tsv txt xls xlsx xml xslfo xslt zip';
    // Cap the upload size according to the PHP limit.
    $maxFilesize = min(
      Bytes::toNumber(Environment::getUploadMaxSize()),
      Bytes::toNumber('8MB')
    );
    $form['attachment'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Attachment'),
      '#description' => $this->t('Attach a file (optional). Max limit: @limit, allowed types: @extensions', [
        '@limit' => ByteSizeMarkup::create($maxFilesize),
        '@extensions' => $extensions,
      ]),
      '#upload_validators' => [
        'file_validate_extensions' => [$extensions],
        'file_validate_size' => [$maxFilesize],
      ],
      '#weight' => 7,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 8,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send e-mail'),
    ];

    if (!empty($this->fieldSettings['modal'])) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => [$this, 'ajaxSubmit'],
        'event' => 'click',
      ];
    }

    if (!$form_state->get('settings')) {
      $this->fieldSettings['default_message'] = '[current-user:name] sent a message using the contact form at [current-page:url].';
      $form_state->set('settings', $this->fieldSettings);
    }

    $this->honeypot?->addFormProtection(
      $form,
      $form_state,
      ['honeypot', 'time_restriction'],
    );

    return $form;
  }

  /**
   * Gets list of option for "Type of Support" field.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   Option list.
   */
  protected function getTypeOfSupportOptions(): array {
    return [
      $this->t('Informational'),
      $this->t('Feedback/Suggestions'),
      $this->t('Technical'),
      $this->t('Other'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->currentUser()->isAnonymous()) {
      return;
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->currentUser()->isAnonymous()) {
      return;
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function sendMessage(array &$form, FormStateInterface $form_state): void {
    $reply_to = $form_state->getValue('mail');
    $settings = $form_state->get('settings');

    $params['subject'] = $form_state->getValue('subject');
    $params['name'] = $form_state->getValue('name');
    $params['organization'] = $form_state->getValue('organization');
    $params['type'] = $this->getTypeOfSupportOptions()[$form_state->getValue('type')]?->render();
    $params['default_message'] = $settings['default_message'];

    $attachment = $this->getAttachedFile($form_state);
    if ($attachment) {
      $params['attachments'][] = [
        'filecontent' => file_get_contents($attachment->getFileUri()),
        'filename' => $attachment->getFileName(),
        'filemime' => $attachment->getMimeType(),
      ];
      $attachment->delete();
    }

    $message = <<<MESSAGE
        Name: {$params['name']} <br/>
        Organization: {$params['organization']} <br />
        Email: $reply_to <br/>
        Type of support: {$params['type']} <br />
        Message:  {$form_state->getValue('message')} <br />
        MESSAGE;
    $params['message'] = Markup::create($message);

    // Send the e-mail to the recipients.
    $to = implode(', ', $this->getEmailAddresses());
    $result = $this->mailManager->mail(
      'email_contact',
      'contact',
      $to,
      $this->currentUser()->getPreferredLangcode(),
      $params,
      $reply_to
    );
    if ($result['result'] !== TRUE) {
      $this->messenger()
        ->addError($this->t('There was a problem sending your message and it was not sent.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Your message has been sent.'));
    $msg = 'Email sent from: @reply_to to: @to about: "@subject" containing: "@message"';
    $this->logger('email_contact')->notice($msg, [
      '@name' => $params['name'],
      '@reply_to' => $reply_to,
      '@to' => $to,
      '@subject' => $params['subject'],
      '@message' => $params['message'],
    ]);
  }

  /**
   * Gets the attached file.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\file\FileInterface|null
   *   File entity.
   */
  protected function getAttachedFile(FormStateInterface $form_state): ?FileInterface {
    if (empty($form_state->getValue('attachment'))) {
      return NULL;
    }

    $fid = reset($form_state->getValue('attachment'));
    return $this->entityTypeManager->getStorage('file')->load($fid);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager'),
      $container->has('honeypot') ? $container->get('honeypot') : NULL
    );
  }

  /**
   * Get mails.
   *
   * @return array
   *   Email addresses where the message will be sent to.
   */
  abstract protected function getEmailAddresses(): array;

}
