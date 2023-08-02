<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Provides an interface for parser plugins.
 */
interface EuOssCatalogueParserInterface extends PluginInspectionInterface {

  /**
   * Checks whether the schema is valid.
   *
   * @param array $data
   *   Data to be checked.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   * @param string|null $providerReference
   *   Some providers are assigning a proprietary ID to the solution. This is
   *   particularly useful because schema errors can be reported back to the
   *   provider.
   *
   * @return bool
   *   TRUE if the schema is valid.
   */
  public function checkSchema(array $data, EuOssCatalogueProviderInterface $provider, ?string $providerReference = NULL): bool;

  /**
   * Parses fetched data and saves it as an OssSolution node.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution $fetchedSolution
   *   The fetched solution object.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface
   *   The new or update OSS Solution node.
   */
  public function parse(EuOssCatalogueFetchedSolution $fetchedSolution, EuOssCatalogueProviderInterface $provider): OssSolutionInterface;

}
