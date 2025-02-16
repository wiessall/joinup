<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Service;

use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Private Message user service.
 */
class PrivateMessageUserService implements PrivateMessageUserServiceInterface {

  public function __construct(
    #[Autowire(service: 'private_message.service')]
    protected PrivateMessageServiceInterface $privateMessageService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getPrivateMessageRecipients(PrivateMessageInterface $privateMessage): ?array {
    $thread = $this->privateMessageService->getThreadFromMessage($privateMessage);
    $owner = $privateMessage->getOwner();

    return array_filter($thread->getMembers(), function (UserInterface $user) use ($owner) {
      return $user !== $owner;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getPrivateMessageRecipient(PrivateMessageInterface $privateMessage): ?UserInterface {
    $recipients = $this->getPrivateMessageRecipients($privateMessage);
    return $recipients ? reset($recipients) : NULL;
  }

  /**
   * {@inheritdoc}
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
