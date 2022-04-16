<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Update the canonical path for the RDF meta entity.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $routes = [
      'entity.rdf_meta_entity.canonical',
      'entity.rdf_meta_entity.edit_form',
      'entity.rdf_meta_entity.delete_form',
    ];

    foreach ($routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        // The "_entity_access" requirement is already there. The following
        // mainly removes the requirement added by core that the entity ID must
        // be numeric.
        $route->setRequirements([
          '_entity_access' => 'rdf_meta_entity.view',
        ]);
      }
    }
  }

}
