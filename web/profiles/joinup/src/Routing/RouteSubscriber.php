<?php

declare(strict_types=1);

namespace Drupal\joinup\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing routes for Joinup specific use cases.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Since all content in Joinup is related to a collection we use custom
    // forms that allow people to add content inside a collection. The standard
    // node forms should not be used for creating new content because the group
    // audience fields are hidden and dangling content would be created that is
    // not associated with any groups. To prevent this we're adding our own
    // access check to those routes here.
    $routes = [
      'node.add',
      'node.add_page',
    ];
    foreach ($routes as $route) {
      if ($route = $collection->get($route)) {
        $route->addRequirements(['_node_creation_route' => 'TRUE']);
      }
    }
  }

}
