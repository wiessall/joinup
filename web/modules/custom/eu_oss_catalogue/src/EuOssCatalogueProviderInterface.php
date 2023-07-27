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
   *   An indexed array of business solutions. Each item is a fetched object.
   */
  public function fetch(): array;

}
