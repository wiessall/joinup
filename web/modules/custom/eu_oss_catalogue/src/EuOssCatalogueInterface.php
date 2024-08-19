<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Interface for 'eu_oss_catalogue' service.
 */
interface EuOssCatalogueInterface {

  /**
   * Checks whether the EU OSS Catalogue functionality is enabled.
   *
   * @return bool
   *   Whether the EU OSS Catalogue functionality is enabled.
   */
  public function isEnabled(): bool;

  /**
   * Fetches and queues a list of business solutions from a provider.
   *
   * @param string $providerId
   *   The provider plugin ID.
   *
   * @return int
   *   The number of fetched OSS solutions.
   *
   * @throws \InvalidArgumentException
   *   If the passed plugin ID is invalid.
   */
  public function fetch(string $providerId): int;

  /**
   * Returns a solution by its URL.
   *
   * @param string $solutionUrl
   *   The solution URL.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface|null
   *   The OSS Solution node, or NULL if not found.
   */
  public function loadBySolutionUrl(string $solutionUrl): ?OssSolutionInterface;

  /**
   * Creates or updates an OSS Solution.
   *
   * @param string $providerPluginId
   *   The provider plugin ID.
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project object.
   */
  public function createOrUpdateSolution(string $providerPluginId, Project $project): void;

  /**
   * Prepares the entities removed from the source in a queue to be processed.
   *
   * @param string $providerPluginId
   *   The provider plugin ID.
   * @param array $solutionUrls
   *   The list of solution URLs to be cleaned up.
   */
  public function prepareCleanup(string $providerPluginId, array $solutionUrls): void;

  /**
   * Cleans up a solution or updates the list of providers.
   *
   * @param string $providerPluginId
   *   The provider plugin ID.
   * @param array $solutionIds
   *   The solution IDs to cleanup.
   */
  public function cleanupOrUpdate(string $providerPluginId, array $solutionIds): void;

}
