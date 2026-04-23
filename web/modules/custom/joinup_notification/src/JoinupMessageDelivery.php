<?php

declare(strict_types=1);

namespace Drupal\joinup_notification;

use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\message\Entity\Message;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifier;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a service class for creating and delivering messages.
 */
readonly class JoinupMessageDelivery implements JoinupMessageDeliveryInterface {

  /**
   * A list of message digest notifier plugin IDs.
   *
   * @var array
   */
  const DIGEST_NOTIFIER_IDS = [
    'daily' => 'message_digest:daily',
    'weekly' => 'message_digest:weekly',
    'monthly' => 'message_digest:monthly',
  ];

  public function __construct(
    #[Autowire(service: 'message_notify.sender')]
    protected readonly MessageNotifier $messageNotifier,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function sendMessageToMultipleUsers(MessageInterface $message, array $accounts, array $notifier_options = [], bool $digest = FALSE): bool {
    $recipients_metadata = [];
    /** @var \Drupal\user\UserInterface $account */
    foreach ($accounts as $account) {
      if (!$this->accountIsValid($account)) {
        continue;
      }

      // By keying on the user ID we can avoid that a user might get the message
      // more than once.
      $recipients_metadata[$account->id()] = [
        'owner' => $account,
        'options' => $notifier_options,
        'notifier' => $digest ? $this->getNotifierId($account) : 'email',
      ];
    }

    return $this->sendMessage($message, $recipients_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessageToUser(MessageInterface $message, UserInterface $account, array $notifier_options = [], bool $digest = FALSE): bool {
    if (!$this->accountIsValid($account)) {
      return FALSE;
    }

    $recipient_metadata = [
      'owner' => $account,
      'options' => $notifier_options,
      'notifier' => $digest ? $this->getNotifierId($account) : 'email',
    ];

    return $this->sendMessage($message, [$recipient_metadata]);
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessageToEmailAddresses(MessageInterface $message, array $mails, array $notifier_options = []): bool {
    $recipients_metadata = [];

    // Ensure uniqueness so that the message is not delivered multiple times to
    // the same address.
    foreach (array_unique($mails) as $mail) {
      $recipients_metadata[] = [
        'options' => $notifier_options + ['mail' => $mail],
        'notifier' => 'email',
      ];
    }

    return $this->sendMessage($message, $recipients_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessageTemplateToMultipleUsers(string $message_template, array $arguments, array $accounts, array $notifier_options = [], array $message_values = []): bool {
    $message = $this->createMessage($message_template, $message_values, $arguments);
    return $this->sendMessageToMultipleUsers($message, $accounts, $notifier_options);
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessageTemplateToEmailAddresses(string $message_template, array $arguments, array $mails, array $notifier_options = [], array $message_values = []): bool {
    $message = $this->createMessage($message_template, $message_values, $arguments);
    return $this->sendMessageToEmailAddresses($message, $mails, $notifier_options);
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessageTemplateToUser(string $message_template, array $arguments, UserInterface $account, array $notifier_options = [], array $message_values = [], bool $digest = FALSE): bool {
    $message = $this->createMessage($message_template, $message_values, $arguments);
    return $this->sendMessageToUser($message, $account, $notifier_options, $digest);
  }

  /**
   * Returns the message digest notifier plugin ID for the given user.
   *
   * Users may configure the frequency they wish to receive a message digest.
   * This returns the corresponding digest notifier plugin ID.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account for which to return the plugin ID.
   *
   * @return string
   *   The plugin ID.
   */
  protected function getNotifierId(UserInterface $account): string {
    $frequency = $account->get('field_user_frequency')->value;
    if (array_key_exists($frequency, self::DIGEST_NOTIFIER_IDS)) {
      return self::DIGEST_NOTIFIER_IDS[$frequency];
    }

    // Use standard email notification if the user didn't choose a frequency.
    return 'email';
  }

  /**
   * Sends the given message to the given recipients.
   *
   * @param \Drupal\message\MessageInterface $message
   *   The message to send.
   * @param array $recipients_metadata
   *   An array of recipient data, each item an associative array with the
   *   following keys:
   *   - owner: (optional) The recipient user account.
   *   - options: An associative array of options for the message notifier.
   *   - notifier: The plugin ID of the message notifier to use.
   *
   * @return bool
   *   Whether or not all messages were successfully sent.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when a message could not be saved.
   */
  protected function sendMessage(MessageInterface $message, array $recipients_metadata): bool {
    // If the message is not saved, do this right now.
    if ($message->isNew()) {
      $message->save();
    }

    $success = TRUE;
    foreach ($recipients_metadata as $recipient_metadata) {
      // Clone the message per recipient to avoid cross-recipient state leakage.
      // The original_message reference allows digest notifiers to retrieve the
      // saved message ID, since createDuplicate() strips the entity ID.
      $send_message = $message->createDuplicate();
      $send_message->original_message = $message;

      // The duplicate is never saved: its sole purpose is a per-recipient send.
      $recipient_metadata['options']['save on success'] = FALSE;
      $recipient_metadata['options']['save on fail'] = FALSE;

      // Prepare the message.
      $owner = $recipient_metadata['owner'] ?? NULL;
      if ($owner instanceof UserInterface) {
        $send_message->setOwner($owner);

        // For announcements, inject per-recipient tokens.
        if ($send_message instanceof AnnouncementInterface) {
          $send_message->addUserArguments($owner);
        }
      }

      // Send the message.
      $success = $this->messageNotifier->send($send_message, $recipient_metadata['options'], $recipient_metadata['notifier']) && $success;
    }
    return $success;
  }

  /**
   * Returns a new Message entity from the given template and arguments.
   *
   * @param string $message_template
   *   The message template to use for creating the message.
   * @param array $values
   *   Array of field values to set on the Message entity.
   * @param array $arguments
   *   The arguments array to set on the message.
   *
   * @return \Drupal\message\MessageInterface
   *   The message.
   */
  protected function createMessage(string $message_template, array $values, array $arguments): MessageInterface {
    $values['template'] = $message_template;
    $message = Message::create($values);
    $message->setArguments($arguments);
    return $message;
  }

  /**
   * Checks that the passed in user account is able to receive e-mail.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account to check.
   *
   * @return bool
   *   Whether the account is valid to receive emails.
   */
  protected function accountIsValid(UserInterface $account): bool {
    return !$account->isAnonymous() && !empty($account->getEmail());
  }

}
