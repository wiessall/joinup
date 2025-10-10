<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Only grants access to UID 1, denies everyone else.
 */
class NodeCreationRoutesAccess implements AccessInterface {

  /**
   * Grants access to standard node creation pages in some circumstances.
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
    $cacheMetadata = (new CacheableMetadata())->addCacheContexts([
      'route',
      'user.permissions',
    ]);

    if ($account->hasPermission('access node creation routes')) {
      return AccessResult::allowed()->addCacheableDependency($cacheMetadata);
    }

    if ($routeMatch->getRouteName() === 'node.add') {
      $nodeType = $routeMatch->getRawParameter('node_type');
      $standaloneNodeTypes = ['licence'];
      if (in_array($nodeType, $standaloneNodeTypes, TRUE)) {
        return AccessResult::allowed()->addCacheableDependency($cacheMetadata);
      }
    }

    return AccessResult::forbidden()->addCacheableDependency($cacheMetadata);
  }

}
