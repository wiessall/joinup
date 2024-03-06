<?php

declare(strict_types=1);

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

  /**
   * Removes a provider from the list of sources.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   *
   * @return $this
   */
  public function removeProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface;

  /**
   * Returns the language code of the richest translation.
   *
   * @return string
   *   The language code of the richest translation.
   */
  public function getRelevantLangCode(): string;

  /**
   * Checks whether the entity is archived.
   *
   * @return bool
   *   Whether this entity is archived.
   */
  public function isArchived(): bool;
}
