<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Entity\EntityInterface;

/**
 * The interface for the JoinupGroupPermissionManager service.
 */
interface JoinupGroupPermissionManagerInterface {

  /**
   * Checks if user has OG permission.
   *
   * @param int|string $uid
   *   The user id to retrieve memberships for.
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group to get the membership for.
   * @param string $permission
   *   The group permission.
   */
  public function userHasOgCollectionPermission(int $uid, EntityInterface $group, string $permission): bool;

}
