<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Interface for 'eu_oss_catalogue' service.
 */
interface EuOssCatalogueInterface {

  /**
   * Fetches and queues a list of business solutions from a provider.
   *
   * @param string $provider
   *   The provider plugin ID.
   *
   * @return int
   *   The number of fetched OSS solutions.
   *
   * @throws \InvalidArgumentException
   *   If the passed plugin ID is invalid.
   */
  public function fetch(string $provider): int;

  /**
   * Creates or updates an OSS Solution.
   *
   * @param string $providerPluginId
   *   The provider plugin ID.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution $fetchedSolution
   *   The fetched solution object.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface
   *   The OSS Solution node.
   */
  public function createOrUpdateSolution(string $providerPluginId, EuOssCatalogueFetchedSolution $fetchedSolution): OssSolutionInterface;

}
