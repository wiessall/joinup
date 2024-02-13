<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\joinup_group\AnnouncementsAccessTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks whether the user can access the announcements route.
 */
class AnnouncementsAccessCheck implements AccessInterface {

  use AnnouncementsAccessTrait;

  /**
   * Checks whether the user can access the announcements route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Exception
   *   When the '_joinup_group_announcements' requirement has an invalid value.
   */
  public function access(Route $route, RouteMatchInterface $route_match): AccessResultInterface {
    $requirement = $route->getRequirement('_joinup_group_announcements');
    if ($requirement !== 'TRUE') {
      throw new \Exception("The '_joinup_group_announcements' route requirement should be 'TRUE', but '$requirement' was given.");
    }
    $group = $route_match->getParameter('rdf_entity');
    if ($group instanceof GroupInterface) {
      return $this->accessAnnouncements($group);
    }
    return AccessResult::neutral();
  }

}
