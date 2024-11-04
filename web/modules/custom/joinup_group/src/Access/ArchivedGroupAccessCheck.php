<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides an access checker for the '_archived_group' requirement.
 */
class ArchivedGroupAccessCheck implements AccessInterface {

  /**
   * Checks access for routes having the '_archived_collection' requirement.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parameterized route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Exception
   *   If the '_archived_group' has an invalid value.
   */
  public function access(Route $route, RouteMatchInterface $route_match): AccessResultInterface {
    $requirement = $route->getRequirement('_archived_group');
    assert($requirement === 'TRUE', 'Invalid value for route "_archived_group" requirement. Expected "TRUE".');
    $group = $route_match->getParameter('node');

    // If the group is archived, access is denied.
    if ($group instanceof GroupInterface && $group->getWorkflowState() === 'archived') {
      return AccessResult::forbidden();
    }

    // @todo Why return allowed here? Why not neutral? Is this the sole access
    //   check for some route?
    return AccessResult::allowed();
  }

}
