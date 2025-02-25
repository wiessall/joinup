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
   * Returns the catalogue content.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $plugin
   *   Provider plugin.
   *
   * @return array
   *   Render array.
   */
  public function getContent(EuOssCatalogueProviderInterface $plugin): array {
    return [
      '#theme' => 'catalogue_provider',
      '#provider' => $plugin,
    ];
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
    return (string) $plugin->getPluginDefinition()['label'];
  }

}
