<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\joinup_core\Controller\JoinupCssAssetController;
use Drupal\joinup_core\Controller\JoinupJsAssetController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters asset routes to use fault-tolerant controllers.
 */
class AssetControllerRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Modifies asset controller routes to make them return empty files on
    // invalid requests.
    $collection->get('system.js_asset')
      ->setDefault('_controller', JoinupJsAssetController::class . '::deliver');
    $collection->get('system.css_asset')
      ->setDefault('_controller', JoinupCssAssetController::class . '::deliver');
  }

}
