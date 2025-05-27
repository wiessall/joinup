<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\joinup_solution\Access\SolutionSettingsViewAccessCheck;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity.meta_entity.canonical')) {
      $route->setRequirement('_custom_access', SolutionSettingsViewAccessCheck::class . '::access');
    }
  }

}
