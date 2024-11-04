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

  public function __construct(protected PrivateMessageServiceInterface $privateMessageService) {}

  /**
   * Gets Private Message target user.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageInterface $privateMessage
   *   Private Message entity.
   *
   * @return \Drupal\user\UserInterface
   *   Private Message target user.
   */
  public function getPrivateMessageTarget(PrivateMessageInterface $privateMessage): ?UserInterface {
    $thread = $this->privateMessageService->getThreadFromMessage($privateMessage);
    $owner = $privateMessage->getOwner();
    $members = $thread->getMembers();
    $target = array_filter($members, function (UserInterface $user) use ($owner) {
      return $user !== $owner;
    });
    return $target ? array_shift($target) : NULL;
  }

}
