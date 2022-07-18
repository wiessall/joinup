<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\message\MessageInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides a route access checker for _entity_access route requirement.
 *
 * Disallow edit sent messages when they are in sent state.
 */
class JoinupAnnouncementsAccessCheck implements AccessInterface {

  /**
   * Checks access based on _entity_access route requirement.
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
    if ($route->getRequirement('_entity_access') === 'message.edit') {
      $message = $route_match->getParameter('message');
      if ($message instanceof MessageInterface) {
        return AccessResult::allowedIf($message->get('state')->value !== 'sent')->addCacheableDependency($message);
      }
    }
    return AccessResult::allowed();
  }

}
