<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Determines whether the requested entity has the specified bundles.
 */
class EntityBundles implements AccessInterface {

  /**
   * Checks whether the entity is one of the bundles.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match): AccessResultInterface {
    $entityTypeId = $route->getDefault('entity_type_id');
    $parameters = $route_match->getParameters();
    if (!$parameters->has($entityTypeId)) {
      return AccessResult::allowed();
    }
    $entity = $parameters->get($entityTypeId);
    $bundleIds = array_filter(
      explode(
        ',',
        $route->getRequirement('_joinup_replicate_entity_bundles') ?? ''
      )
    );
    return AccessResult::allowedIf(empty($bundleIds))
      ->orIf(AccessResult::allowedIf(
        in_array($entity->bundle(), $bundleIds, TRUE)
      ));
  }

}
