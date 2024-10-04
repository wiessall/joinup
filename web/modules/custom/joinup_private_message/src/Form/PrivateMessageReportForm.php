<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Service\PrivateMessageBanManager;
use Drupal\private_message\Service\PrivateMessageService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a  Private Message Report form.
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
    protected PrivateMessageBanManager $privateMessageBanManager,
    protected MailManager $mailManager,
    protected PrivateMessageService $privateMessageService,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Static call of PrivateMessafeReportForm.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Param container.
   *
   * @return \Drupal\private_message\Entity\PrivateMessageReportForm
   *   PrivateMessageReportForm value.
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
   * Gets form id.
   *
   * @return string
   *   returns string.
   */
  public function getFormId(): string {
    return 'private_message_report_form';
  }

  /**
   * Form builder.
   *
   * @param array $form
   *   Form param.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   From state param.
   *
   * @return array
   *   Returns array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Textarea for the report.
    $form['report'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Report'),
      '#description' => $this->t('Enter your report message.'),
      '#required' => TRUE,
    ];

    // Checkbox for blocking the sender.
    $form['block_sender'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Also block this sender'),
      '#default_value' => 0,
    ];

    // Submit button.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Foom submission.
   *
   * @param array $form
   *   Form param.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state param.
   *
   * @return void
   *   Returns void.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $report = $form_state->getValue('report');
    $blockSender = $form_state->getValue('block_sender');
    $messageId = $this->getRouteMatch()->getParameter('private_message');
    $message = PrivateMessage::load($messageId);
    $sender = $message->getOwner();
    $recipient = $this->currentUser();

    // Send email to moderators.
    $params = [
      'subject' => $this->t('User @reporter reported a message', ['@reporter' => $recipient->getDisplayName()]),
      'message' => $this->t(
        'The user <a href="@reporter_url">@reporter_name</a> has reported user <a href="@sender_url">@sender_name</a> for sending an unwanted message (<a href="@message_url">view message</a>):<br>
    <h5>Message content: </h5>',
        [
          '@reporter_name' => $recipient->getDisplayName(),
          '@reporter_url' => Url::fromRoute('entity.user.canonical', ['user' => $recipient->id()], ['absolute' => TRUE])->toString(),
          '@sender_name' => $sender->getDisplayName(),
          '@sender_url' => Url::fromRoute('entity.user.canonical', ['user' => $sender->id()], ['absolute' => TRUE])->toString(),
          '@message_url' => Url::fromRoute('entity.private_message.canonical', ['private_message' => $messageId], ['absolute' => TRUE])->toString(),
        ]
      ) . $message->get('message')->value,
    ];
    $moderators = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'moderator']);
    // Send the email.
    foreach ($moderators as $moderator) {
      $this->mailManager->mail('joinup_private_message', 'report_message', $moderator->getEmail(), 'en', $params);
    }
    $this->messenger()->addMessage($this->t('Your report has been sent: @message', ['@message' => $report]));

    // Ban user if checked.
    if ($blockSender) {
      $this->privateMessageBanManager->banUser((int) $sender->id());
    }

    $form_state->setRedirect('private_message.private_message_page');
  }

}
