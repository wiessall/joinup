<?php

declare(strict_types=1);

namespace Drupal\joinup\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing routes related to admin/reporting pages.
 */
class ReportingRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $routes = [
      'view.moderator_solutions_by_type.page_1',
      'view.moderator_solutions_by_type.csv_export',
    ];

    foreach ($routes as $routeName) {
      if ($route = $collection->get($routeName)) {
        $route->setOption('_admin_route', FALSE);
      }
    }
  }

}
