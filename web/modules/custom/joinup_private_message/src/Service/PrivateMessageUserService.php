<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\UserInterface;

/**
 * Private Message user service.
 */
class PrivateMessageUserService {

  /**
   * {@inheritdoc}
   */
  public function __construct(protected PrivateMessageServiceInterface $privateMessageService) {}

  /**
   * Gets recipients of Private Message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private Message entity.
   *
   * @return \Drupal\user\UserInterface[]
   *   Private Message recipients.
   */
  public function getPrivateMessageRecipients(PrivateMessageInterface $privateMessage): ?array {
    $thread = $this->privateMessageService->getThreadFromMessage($privateMessage);
    $owner = $privateMessage->getOwner();
    $members = $thread->getMembers();
    $recipients = array_filter($members, function (UserInterface $user) use ($owner) {
      return $user !== $owner;
    });
    return $recipients ?? NULL;
  }

  /**
   * Gets first recipient of Private Message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private Message entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   Private Message recipients.
   */
  public function getPrivateMessageRecipient(PrivateMessageInterface $privateMessage): ?UserInterface {
    $recipients = $this->getPrivateMessageRecipients($privateMessage);
    return $recipients ? array_shift($recipients) : NULL;
  }

  /**
   * Checks if user is a recipient of Private Message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private Message entity.
   * @param int $userId
   *   User id.
   *
   * @return bool
   *   If user id is in recipients array, return TRUE.
   */
  public function isRecipient(PrivateMessageInterface $privateMessage, int $userId): bool {
    $recipients = $this->getPrivateMessageRecipients($privateMessage);
    if (empty($recipients)) {
      return FALSE;
    }
    foreach ($recipients as $recipient) {
      if ((int) $recipient->id() === $userId) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
