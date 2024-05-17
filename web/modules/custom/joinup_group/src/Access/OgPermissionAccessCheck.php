<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * OG permission access check.
 */
class OgPermissionAccessCheck implements AccessInterface {

  /**
   * Constructs a OgPermissionAccessCheck instance.
   *
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager
   *   The group manager service.
   */
  public function __construct(
    protected JoinupGroupManagerInterface $groupManager,
  ) {
  }

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account): AccessResultInterface {
    $permission = $route->getRequirement('_og_permission');
    if ($permission === NULL) {
      return AccessResult::neutral();
    }

    $group = $this->groupManager->getGroupFromContext();
    if (!$group) {
      return AccessResult::forbidden('Group is missing');
    }

    return $this->groupManager->access($group, $permission, $permission, $account);
  }

}
