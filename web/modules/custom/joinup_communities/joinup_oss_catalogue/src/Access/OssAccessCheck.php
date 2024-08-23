<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks OSS group access route.
 */
class OssAccessCheck implements AccessInterface {

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match): AccessResultInterface {
    $requirement = $route->getRequirement('_oss_access_check');
    if ($requirement !== 'TRUE') {
      throw new \Exception("The '_oss_access_check' route requirement should be 'TRUE', but '$requirement' was given.");
    }

    $group = $route_match->getParameter('node');
    if ($group instanceof GroupInterface) {
      return AccessResult::allowedIf($group->id() !== OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID)
        ->addCacheTags(Cache::buildTags('og-group-content', $group->getCacheTags()))
        ->addCacheContexts(['og_group_context']);
    }
    return AccessResult::neutral();
  }

}
