<?php

declare(strict_types = 1);

namespace Drupal\joinup_poll\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Poll route subscriber.
 */
class PollRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $pollRoutes = [
      'poll.poll_add',
      'entity.poll.edit_form',
      'entity.poll.delete_form',
      'entity.poll.canonical',
    ];

    // Disable all canonical poll's routes.
    foreach ($pollRoutes as $pollRoute) {
      if ($route = $collection->get($pollRoute)) {
        $route->setRequirement('_permission', 'administer polls');
      }
    }
  }

}
