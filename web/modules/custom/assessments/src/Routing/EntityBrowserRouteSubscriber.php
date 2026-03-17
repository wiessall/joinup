<?php

declare(strict_types=1);

namespace Drupal\assessments\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Hides the title on the assessment solutions entity browser page.
 */
class EntityBrowserRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity_browser.assessment_solutions')) {
      $route->setDefault('_title', '');
      $route->setDefault('_title_callback', '');
    }
  }

}
