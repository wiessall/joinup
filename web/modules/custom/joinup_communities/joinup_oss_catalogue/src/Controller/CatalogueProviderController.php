<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;

/**
 * Provides a controller for 'joinup_oss_catalogue.catalogue_provider' route.
 */
class CatalogueProviderController extends ControllerBase {

  /**
   * Returns a renderable array for a test page.
   *
   * Return []
   */
  public function content(EuOssCatalogueProviderInterface $plugin): array {
    $build = [
      '#markup' => $plugin->getPluginId(),
    ];

    return $build;
  }

}
