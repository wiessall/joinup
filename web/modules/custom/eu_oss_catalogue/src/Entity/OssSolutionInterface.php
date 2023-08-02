<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\node\NodeInterface;

/**
 * Interface of 'oss_solution' nodes.
 */
interface OssSolutionInterface extends NodeInterface {

  /**
   * Appends a new provider to the source field.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   *
   * @return $this
   */
  public function addProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface;

}
