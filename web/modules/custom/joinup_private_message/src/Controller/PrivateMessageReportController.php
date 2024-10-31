<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_private_message\Service\PrivateMessageUserService;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Private Message report.
 */
class PrivateMessageReportController implements ContainerInjectionInterface {

  public function __construct(protected PrivateMessageUserService $privateMessageUserService, protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('joinup_private_message.user_service'),
      $container->get('current_user'));
  }

  /**
   * Custom access check for viewing the private message report.
   */
  public function access(PrivateMessageInterface $private_message): AccessResultInterface {
    $privateMessageTarget = $this->privateMessageUserService->getPrivateMessageTarget($private_message);
    if ($privateMessageTarget instanceof UserInterface && $privateMessageTarget->id() == $this->currentUser->id()) {
      return AccessResult::allowed()
        ->addCacheableDependency($privateMessageTarget);
    }
    return AccessResult::forbidden()
      ->addCacheableDependency($privateMessageTarget)
      ->setReason('Access denied: You are not the target user for this private message.');
  }

}
