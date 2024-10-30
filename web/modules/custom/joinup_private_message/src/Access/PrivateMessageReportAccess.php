<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_private_message\Service\PrivateMessageService;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Checks access for reporting messages.
 */
class PrivateMessageReportAccess implements AccessInterface {

  public function __construct(protected AccountInterface $currentUser, protected PrivateMessageService $privateMessageService) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('joinup_private_message.service')
    );
  }

  /**
   * Check access for the private message target user.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $private_message
   *   The private message entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(PrivateMessage $private_message): AccessResultInterface {
    $privateMessageTarget = $this->privateMessageService->getPrivateMessageTarget($private_message);
    if ($privateMessageTarget instanceof UserInterface && $privateMessageTarget->id() == $this->currentUser->id()) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
