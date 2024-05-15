<?php

declare(strict_types=1);

namespace Drupal\joinup\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Only grants access to UID 1, denies everyone else.
 */
class Uid1OnlyAccess implements AccessInterface {

  /**
   * Grants access only to UID 1.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RouteMatchInterface $routeMatch, AccountInterface $account): AccessResultInterface {
    if ($account->id() == 1) {
      return AccessResult::allowed()->addCacheContexts(['user.is_super_user']);
    }

    if ($routeMatch->getRouteName() === 'node.add') {
      $nodeType = $routeMatch->getRawParameter('node_type');
      $standaloneNodeTypes = ['licence'];
      if (in_array($nodeType, $standaloneNodeTypes, TRUE)) {
        return AccessResult::allowed()->addCacheContexts(['user.is_super_user']);
      }
    }

    return AccessResult::forbidden()->addCacheContexts(['user.is_super_user']);
  }

}
