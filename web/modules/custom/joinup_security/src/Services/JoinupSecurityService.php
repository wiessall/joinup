<?php

declare(strict_types=1);

namespace Drupal\joinup_security\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_group\JoinupGroupPermissionManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Joinup security service.
 */
class JoinupSecurityService {

  use AutowireTrait;

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'Drupal\joinup_group\JoinupGroupPermissionManagerInterface')]
    protected readonly JoinupGroupPermissionManagerInterface $joinupGroupManagerPermissions,
    protected TimeInterface $time,
  ) {}

  /**
   * Checks if the current user can bypass the node creation limit.
   *
   * @return bool
   *   TRUE if the user can bypass the limit
   *   or has less than 2 community content items created within the last 24 h,
   *   FALSE otherwise.
   */
  public function currentUserHasNodeCreationLimit($node): bool {
    $timestamp24HoursAgo = $this->time->getRequestTime() - 86400;
    $uid = $this->currentUser->id();
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', CommunityContentHelper::BUNDLES, 'IN')
      ->condition('uid', $uid)
      ->condition('created', $timestamp24HoursAgo, '>=')
      ->accessCheck(TRUE)
      ->count();

    $count = $query->execute();
    return $this->currentUser->hasPermission('bypass node creation limit') ||
      $this->joinupGroupManagerPermissions->userHasOgCollectionPermission($uid, $node, 'bypass node creation limit') || $count < 2;
  }

}
