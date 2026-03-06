<?php

declare(strict_types=1);

namespace Drupal\assessments\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters entity browser routes for assessments.
 */
class AssessmentsRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity_browser.assessment_solutions')) {
      $route->setRequirement('_assessments_asset_creation_access', 'TRUE');
    }
  }

}
