<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\user\UserInterface;

/**
 * The Private Message user service interface.
 */
interface PrivateMessageUserServiceInterface {

  /**
   * Gets recipients of the private message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private Message entity.
   *
   * @return \Drupal\user\UserInterface[]
   *   Private message recipients.
   */
  public function getPrivateMessageRecipients(PrivateMessageInterface $privateMessage): ?array;

  /**
   * Gets first recipient of the private message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private Message entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   Private Message recipients.
   */
  public function getPrivateMessageRecipient(PrivateMessageInterface $privateMessage): ?UserInterface;

  /**
   * Checks if user is a recipient of the private message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private message entity.
   * @param int $userId
   *   User id.
   *
   * @return bool
   *   If user id is in recipients array, return TRUE.
   */
  public function isRecipient(PrivateMessageInterface $privateMessage, int $userId): bool;

}
