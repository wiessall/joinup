<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate\RouteSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\joinup_group\Controller\GroupNodeController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Decorates Replicate UI routes by adding additional requirements.
 *
 * @see \Drupal\replicate_ui\RouteSubscriber
 */
class RouteSubscriber extends RouteSubscriberBase {

  protected const array ALLOWED_BUNDLES_PER_TYPE = [
    'node' => [
      'custom_page',
      'document',
      'event',
      'news',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // @todo Consider making this configurable by additional configuration
    // options either in Joinup or in the contrib module.
    foreach (static::ALLOWED_BUNDLES_PER_TYPE as $entityTypeId => $allowedBundles) {
      if (!$route = $collection->get("entity.$entityTypeId.replicate")) {
        continue;
      }

      $parameters = $route->getOption('parameters') ?? [];
      $parameters['node']['bundle'] = $allowedBundles;
      $route->setOption('parameters', $parameters);

      // Override requirements.
      $route->setRequirements([
        '_custom_access' => GroupNodeController::class . '::nodeCreateAccessFromNode',
        '_joinup_replicate_permission' => 'replicate entities',
      ]);
    }
  }

}
