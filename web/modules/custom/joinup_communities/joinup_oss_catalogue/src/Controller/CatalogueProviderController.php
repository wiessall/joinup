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
   * Returns a renderable array.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $plugin
   *   Provider plugin.
   *
   * @return array
   *   Render array.
   */
  public function getContent(EuOssCatalogueProviderInterface $plugin): array {
    $build = [
      '#markup' => $plugin->getPluginId(),
    ];

    return $build;
  }

  /**
   * Gets the title of the page.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $plugin
   *   Provide plugin.
   *
   * @return string
   *   Title.
   */
  public function getTitle(EuOssCatalogueProviderInterface $plugin): string {
    return $plugin->getPluginDefinition()['label']->__toString();
  }

}
