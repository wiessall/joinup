<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Service\PrivateMessageBanManager;
use Drupal\private_message\Service\PrivateMessageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Private Message Report form.
 */
class PrivateMessageReportForm extends FormBase {

  /**
   * Constructs a PrivateMessageReportForm object.
   *
   * @param \Drupal\private_message\Service\PrivateMessageBanManager $privateMessageBanManager
   *   PrivateMessageBanManager service.
   * @param \Drupal\Core\Mail\MailManager $mailManager
   *   MailManager service.
   * @param \Drupal\private_message\Service\PrivateMessageService $privateMessageService
   *   PrivateMessageService service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   EntityTypeManagerInterface service.
   */
  public function __construct(
    protected readonly PrivateMessageBanManager $privateMessageBanManager,
    protected readonly MailManager $mailManager,
    protected readonly PrivateMessageService $privateMessageService,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): PrivateMessageReportForm|static {
    return new static(
      $container->get('private_message.ban_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('private_message.service'),
      $container->get('entity_type.manager'),
    );
  }

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

    $form['report'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Report'),
      '#description' => $this->t('Enter your report message.'),
      '#required' => TRUE,
    ];

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
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $report = $form_state->getValue('report');
    $blockSender = $form_state->getValue('block_sender');
    $message = $form['private_message']['#value'];
    $sender = $message->getOwner();
    $params = [
      'sender' => $sender,
      'recipient' => $this->currentUser(),
      'message' => $message,
    ];
    $moderators = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'moderator']);
    // Send the email.
    foreach ($moderators as $moderator) {
      $this->mailManager->mail('joinup_private_message', 'report_message', $moderator->getEmail(), 'en', $params);
    }
    $this->messenger()->addStatus($this->t('Your report has been sent: @message', ['@message' => $report]));
    // Ban user if checked.
    if ($blockSender) {
      $this->privateMessageBanManager->banUser((int) $sender->id());
    }
    $form_state->setRedirect('private_message.private_message_page');
  }

}
