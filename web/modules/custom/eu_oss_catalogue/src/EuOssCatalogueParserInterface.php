<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\FetchedSolution;

/**
 * Provides an interface for parser plugins.
 */
interface EuOssCatalogueParserInterface extends PluginInspectionInterface {

  /**
   * Checks whether the schema is valid and returns a list of error messages.
   *
   * @param \Drupal\eu_oss_catalogue\Model\FetchedSolution $fetchedSolution
   *   The fetched solution object.
   *
   * @return array
   *   A list of errors. Empty array means no errors.
   */
  public function checkSchema(FetchedSolution $fetchedSolution): array;

  /**
   * Parses fetched data and saves it as an OssSolution node.
   *
   * @param \Drupal\eu_oss_catalogue\Model\FetchedSolution $fetchedSolution
   *   The fetched solution object.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface
   *   The new or update OSS Solution node.
   */
  public function parse(FetchedSolution $fetchedSolution, EuOssCatalogueProviderInterface $provider): OssSolutionInterface;

}
