<?php

declare(strict_types=1);

namespace Drupal\joinup_security\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_group\JoinupGroupPermissionManagerInterface;

/**
 * Joinup security service.
 */
class JoinupSecurityService {

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JoinupGroupPermissionManagerInterface $joinupGroupManagerPermissions,
    protected TimeInterface $time,
  ) {}

  /**
   * Checks if the current user should be limited when trying to create a node.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   Parent collection.
   *
   * @return bool
   *   FALSE if the user can bypass the limit
   *    or has less than 2 community content items created within the last 24 h,
   *    TRUE otherwise.
   */
  public function applyNodeCreationLimit(CollectionInterface $collection): bool {
    $uid = $this->currentUser->id();
    if (
      $this->currentUser->hasPermission('bypass node creation limit') ||
      $this->joinupGroupManagerPermissions->userHasOgPermission($uid, $collection, 'bypass node creation limit')
    ) {
      return FALSE;
    }

    $timestamp24HoursAgo = $this->time->getRequestTime() - 86400;
    $uid = $this->currentUser->id();
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE);
    $query->condition('type', CommunityContentHelper::BUNDLES, 'IN')
      ->condition('uid', $uid)
      ->condition('created', $timestamp24HoursAgo, '>=')
      ->count();

    $count = $query->execute();
    return $count >= 2;
  }

}
