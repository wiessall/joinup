<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ([
      'entity.node.edit_form',
      'entity.node.delete_form',
    ] as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_certificate_access', 'TRUE');
      }
    }
  }

}
