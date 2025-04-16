<?php

declare(strict_types=1);

namespace Drupal\joinup_security\Services;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\joinup_community_content\CommunityContentHelper;

/**
 *
 */
class JoinupSecurityService {

  use AutowireTrait;

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Checks if the current user can bypass the node creation limit.
   *
   * @return bool
   *   TRUE if the user can bypass the limit
   *   or has less than 2 community content items created within the last 24 h,
   *   FALSE otherwise.
   */
  public function currentUserHasNodeCreationLimit(): bool {
    $timestamp24HoursAgo = \Drupal::time()->getRequestTime() - 86400;
    $uid = $this->currentUser->id();
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', CommunityContentHelper::BUNDLES,'IN')
      ->condition('uid', $uid)
      ->condition('created', $timestamp24HoursAgo, '>=')
      ->accessCheck(TRUE)
      ->count();

    $count = $query->execute();
    return $this->currentUser->hasPermission('bypass node creation limit') || $count < 2;
  }

}
