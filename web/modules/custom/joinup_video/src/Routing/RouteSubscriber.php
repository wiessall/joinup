<?php

declare(strict_types = 1);

namespace Drupal\joinup_video\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\joinup_video\Controller\JoinupVideoController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing Media routes for Joinup.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $routes = [
      'entity.media.add_page',
      'entity.media.add_form',
    ];
    foreach ($routes as $route) {
      if ($route = $collection->get($route)) {
        $route->setRequirement('_custom_access', JoinupVideoController::class . '::access');
      }
    }
  }

}
