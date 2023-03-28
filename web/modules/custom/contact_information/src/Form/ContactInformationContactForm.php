<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Form;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\email_contact\Form\ContactForm;
use Drupal\honeypot\HoneypotServiceInterface;

/**
 * Default class to build the Contact Form.
 */
class ContactInformationContactForm extends ContactForm {

  /**
   * Constructs a new contact information form.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   Mail manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\honeypot\HoneypotServiceInterface|null $honeypot
   *   Honeypot service.
   * @param string|NULL $entityType
   *   Entity type
   * @param string|NULL $entityId
   *   Entity id.
   * @param string|NULL $fieldName
   *   Field name.
   * @param array|NULL $fieldSettings
   *   Field settings.
   */
  public function __construct(
    protected MailManagerInterface $mailManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ?HoneypotServiceInterface $honeypot,
    string $entityType = NULL,
    string $entityId = NULL,
    string $fieldName = NULL,
    array $fieldSettings = NULL,
  ) {
    parent::__construct($entityType, $entityId, $fieldName, $fieldSettings);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $form['actions'] = ['#type' => 'actions'];
    $form['organization'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Organization'),
      '#maxlength' => 255,
      '#weight' => -1,
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'option1' => $this->t('Option 1'),
        'option2' => $this->t('Option 2'),
        'option3' => $this->t('Option 3'),
        'option4' => $this->t('Option 4'),
      ],
      '#required' => TRUE,
    ];

    // Cap the upload size according to the PHP limit.
    $maxFilesize = min(
      Bytes::toNumber(Environment::getUploadMaxSize()),
      Bytes::toNumber('8MB')
    );
    $form['attachment'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Attachment'),
      '#description' => $this->t('Attach a file (optional). Max limit: 8MB, allowed types: azw css csv dbf doc docx dtd.sgml dtd.xml e00 eps epub fmx2 fmx3 fmx4 gif gml gzip indd jpeg jpg json kml kmz mdb mobi mop mxd odf ods odt op.datpro owl pdf pdfa1a pdfa1b pdfx png pps ppsx ppt pptx ps psd rdf rdf.turtle rdf.xml rss rtf schema.xml sgml skos.xml sparqlq sparqlqres tar tar.gz tar.xz tiff tsv txt xls xlsx xml xslfo xslt zip'),
      '#upload_validators' => [
        'file_validate_extensions' => [
          'azw css csv dbf doc docx dtd.sgml dtd.xml e00 eps epub fmx2 fmx3 fmx4 gif gml gzip indd jpeg jpg json kml kmz mdb mobi mop mxd odf ods odt op.datpro owl pdf pdfa1a pdfa1b pdfx png pps ppsx ppt pptx ps psd rdf rdf.turtle rdf.xml rss rtf schema.xml sgml skos.xml sparqlq sparqlqres tar tar.gz tar.xz tiff tsv txt xls xlsx xml xslfo xslt zip',
        ],
        'file_validate_size' => [$maxFilesize],
      ],
    ];

    if ($this->currentUser()->isAnonymous()) {
      // Remove standard form elements.
      unset($form['name'], $form['mail'], $form['subject'], $form['message'], $form['organization'], $form['type'], $form['attachment']);

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

    }
    else {
      // Move 'submit' under 'actions'.
      $form['actions']['submit'] = $form['submit'];

      $this->honeypot?->addFormProtection(
        $form,
        $form_state,
        ['honeypot', 'time_restriction'],
      );
    }

    // @todo Replace this check with ::isModal() when issue #2661046 is in.
    // @see https://www.drupal.org/node/2661046
    if ($this->getRequest()->query->get(MainContentViewSubscriber::WRAPPER_FORMAT) === 'drupal_ajax') {
      $form['actions']['cancel'] = [
        '#type' => 'button',
        '#value' => $this->t('Cancel'),
        '#attributes' => ['class' => ['dialog-cancel']],
        // Put the cancel button to the left of the confirmation button, so it
        // is consistent with the dialog shown when joining the group.
        '#weight' => -1,
      ];
    }

    unset($form['submit']);

    // Vary the form's cache by whether the user is logged in or not.
    (new CacheableMetadata())
      ->setCacheContexts(['user.roles:authenticated'])
      ->applyTo($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->isAnonymous()) {
      parent::validateForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function sendMessage(array &$form, FormStateInterface $form_state): void {
    $emails = unserialize($form_state->getValue('emails'), ['allowed_classes' => FALSE]);
    $reply_to = $form_state->getValue('mail');
    $settings = $form_state->get('settings');
    $params['subject'] = $form_state->getValue('subject');
    $params['name'] = $form_state->getValue('name');
    $params['organization'] = $form_state->getValue('organization');
    $params['type'] = $form_state->getValue('type');
    $params['default_message'] = $settings['default_message'];
    $message = '';

    if (!empty($form_state->getValue('attachment'))) {
      $fid = reset($form_state->getValue('attachment'));
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      $file_uri = $file->getFileUri();
      $file_name = $file->getFileName();
      $file_mime = $file->getMimeType();
      $file_content = file_get_contents($file_uri);
      $params['attachments'][] = [
        'filecontent' => $file_content,
        'filename' => $file_name,
        'filemime' => $file_mime,
      ];
    }
    if ($settings['include_values']) {
      $type_value = $params['type'];
      $message .= 'Name: ' . $params['name'] . '<br/>' . 'Email: ' . $reply_to . '<br/>' . '<br />Organization: ' . $params['organization'] . '<br />Type: ' . $form['type']['#options'][$type_value];
    }
    $message .= '<br/>Message: ' . $form_state->getValue('message');
    $params['message'] = Markup::create($message);

    // Send the e-mail to the recipients.
    $to = implode(', ', $emails);
    $module = 'email_contact';
    $key = 'contact';
    $langcode = $this->currentUser()->getPreferredLangcode();

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, $reply_to);
    if ($result['result'] !== TRUE) {
      $this->messenger()
        ->addError($this->t('There was a problem sending your message and it was not sent.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Your message has been sent.'));
      $msg = 'Email sent from: @replyto to: @to about: "@subject" containing: "@message"';
      $this->logger('email_contact')->notice($msg, [
        '@name' => $params['name'],
        '@replyto' => $reply_to,
        '@to' => $to,
        '@subject' => $params['subject'],
        '@message' => $params['message'],
      ]);
    }
  }

}
