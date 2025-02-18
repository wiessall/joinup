<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\joinup_private_message\Service\PrivateMessageUserServiceInterface;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Service\PrivateMessageBanManager;
use Drupal\private_message\Service\PrivateMessageService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a Private Message Report form.
 */
class PrivateMessageReportForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'private_message.ban_manager')]
    protected readonly PrivateMessageBanManager $privateMessageBanManager,
    protected readonly MailManager $mailManager,
    #[Autowire(service: 'private_message.service')]
    protected readonly PrivateMessageService $privateMessageService,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TimeInterface $time,
    #[Autowire(service: 'joinup_private_message.user_service')]
    protected readonly PrivateMessageUserServiceInterface $privateMessageUserService,
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'private_message_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PrivateMessage $private_message = NULL): array {
    $form['validation_message'] = [
      '#type' => 'markup',
      '#markup' => '<div id="validation-message"></div>',
    ];

    $form['report'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Report'),
      '#format' => 'plain_text',
      '#allowed_formats' => ['plain_text'],
      '#required' => TRUE,
    ];

    // Hide text format guidelines.
    $form['#after_build'][] = function (array $element) {
      unset($element['report']['format']['guidelines']);
      unset($element['report']['format']['help']);
      return $element;
    };

    $form['block_sender'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Also block this sender'),
      '#default_value' => FALSE,
    ];

    $form['private_message'] = [
      '#type' => 'value',
      '#value' => $private_message,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmitHandler',
      ],
    ];

    return $form;
  }

  /**
   * Handles submissions using ajax.
   *
   * @param array $form
   *   Form param.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state param.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Displays validation message or redirects to private messages list.
   */
  public function ajaxSubmitHandler(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->getErrors()) {
      $error_messages = [];
      foreach ($form_state->getErrors() as $error_message) {
        $error_messages[] = $this->t('@message', [
          '@message' => $error_message,
        ]);
      }
      $this->messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
      $response->addCommand(
        new HtmlCommand(
          '#validation-message',
          '<div class="alert alert-danger">' . implode('<br>', $error_messages) . '</div>'
        )
      );
    }
    else {
      $response->addCommand(new RedirectCommand(
        Url::fromRoute('private_message.private_message_page')->toString()
      ));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->deleteByType(MessengerInterface::TYPE_ERROR);
    $report = $form_state->getValue('report');
    $blockSender = $form_state->getValue('block_sender');

    $message = $form_state->getValue('private_message');
    assert($message instanceof PrivateMessageInterface);

    // Update custom fields in private message entity.
    $message
      ->set('report', $report)
      ->set('report_timestamp', $this->time->getCurrentTime())
      ->save();

    $sender = $message->getOwner();
    $params = [
      'sender' => $sender,
      'recipient' => $this->currentUser(),
      'message' => $message,
      'report' => $report,
    ];
    $moderators = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['roles' => 'moderator']);
    // Send the email.
    foreach ($moderators as $moderator) {
      $this->mailManager->mail('joinup_private_message', 'report_message', $moderator->getEmail(), 'en', $params);
    }
    $this->messenger()
      ->addStatus($this->t('Thank you, your report was sent to the moderator team for further investigation.'));
    // Ban user if checked.
    if ($blockSender) {
      $this->privateMessageBanManager->banUser((int) $sender->id());
      $this->messenger()
        ->addStatus($this->t('You will not receive anymore messages from %name.', [
          '%name' => $sender->getDisplayName(),
        ]));
    }
  }

  /**
   * Custom access check for ability to report the private message.
   */
  public function access(PrivateMessageInterface $private_message): AccessResultInterface {
    if ($this->privateMessageUserService->isRecipient($private_message, (int) $this->currentUser->id())) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden()
      ->setReason('Access denied: You are not the target user for this private message.');
  }

}
