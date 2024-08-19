<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to routes building.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $routeNames = [
      'view.eu_oss_catalogue_admin.admin',
      'view.eu_oss_catalogue_admin.page',
      'view.eu_oss_catalogue_log.page',
      'view.search_oss_catalogue.search',
      'entity.rdf_entity.canonical',
    ];
    foreach ($routeNames as $routeName) {
      if ($route = $collection->get($routeName)) {
        $route->setRequirement('_eu_oss_catalogue_check_enabled', 'TRUE');
      }
    }
  }

}
