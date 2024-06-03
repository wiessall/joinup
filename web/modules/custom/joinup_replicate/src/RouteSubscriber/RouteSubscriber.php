<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate\RouteSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Decorates Replicate UI routes by adding additional requirements.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // @todo Make these configurable by additional configuration options.
    if (!($route = $collection->get('entity.node.replicate'))) {
      return;
    }

    $route->addRequirements(
      $route->getRequirements() + [
        // Do not forget: allowed bundles should be a comma separated string
        // if we need to allow replication at multiple node types.
        '_joinup_replicate_entity_bundles' => 'custom_page',
      ]
    );
  }

}
