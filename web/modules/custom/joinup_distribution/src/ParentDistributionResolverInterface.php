<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution;

/**
 * Interface for a service that resolves the distributions of a parent entity.
 */
interface ParentDistributionResolverInterface {

  /**
   * Fetches the IDs of the distributions of a given parent entity ID.
   *
   * @param string $parentId
   *   The ID of the parent entity.
   *
   * @return array
   *   An array of distribution IDs.
   */
  public function getParentsDistributionIds(string $parentId): array;

  /**
   * Fetches the distributions of a given parent entity ID.
   *
   * @param string $parentId
   *   The ID of the parent entity.
   *
   * @return array
   *   An array of distribution entities.
   */
  public function getParentsDistributions(string $parentId): array;

}
