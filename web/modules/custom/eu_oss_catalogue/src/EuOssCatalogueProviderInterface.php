<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for EuOssCatalogProvider plugins.
 */
interface EuOssCatalogueProviderInterface extends PluginInspectionInterface {

  /**
   * Fetches solutions from provider.
   *
   * @return \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution[]
   *   Indexed array of fetched solution objects.
   */
  public function fetch(): array;

}
