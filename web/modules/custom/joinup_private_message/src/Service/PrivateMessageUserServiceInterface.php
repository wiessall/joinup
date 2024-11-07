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
   * Gets Private Message target user.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private message entity.
   *
   * @return \Drupal\user\UserInterface|null
   *   Target user of Private Message.
   */
  public function getPrivateMessageTarget(PrivateMessageInterface $privateMessage): ?UserInterface;

  /**
   * Checks if user is a recipient of private message.
   *
   * @param int $userId
   *   User id param.
   *
   * @return bool
   *   If user is a private message recipient, return TRUE.
   */
  public function isRecipient(int $userId): bool;

}
