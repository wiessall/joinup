<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

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
  public function fetch(): iterable;

  /**
   * Alters the solution created by the parser before is saved.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution node entity.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution $fetchedSolution
   *   The fetched solution object.
   */
  public function alterSolution(OssSolutionInterface $solution, EuOssCatalogueFetchedSolution $fetchedSolution): void;

}
