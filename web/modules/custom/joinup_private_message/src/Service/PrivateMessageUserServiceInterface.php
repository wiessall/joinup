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
   * @return \Drupal\user\UserInterface
   *   Target user of Private Message.
   */
  public function getPrivateMessageTarget(PrivateMessageInterface $privateMessage): ?UserInterface;

}
