<?php

declare(strict_types=1);

namespace Drupal\joinup_entity_browser\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Marks entity browser routes for create access bypass.
 *
 * @see \Drupal\joinup_entity_browser\EntityBrowserNodeAccessControlHandler
 */
class EntityBrowserRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($collection as $name => $route) {
      if (str_starts_with($name, 'entity_browser.')) {
        $route->setDefault('_entity_browser_create_access', TRUE);
      }
    }
  }

}
