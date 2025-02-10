<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\joinup_user\Controller\JoinupUserAutocompleteController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing routes related to users.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity.user.canonical')) {
      $route->addDefaults([
        '_title_callback' => 'joinup_user_canonical_title',
      ]);
    }
    if ($route = $collection->get('system.entity_autocomplete')) {
      $route->setDefault('_controller', JoinupUserAutocompleteController::class . '::handleAutocomplete');
    }
  }

}
