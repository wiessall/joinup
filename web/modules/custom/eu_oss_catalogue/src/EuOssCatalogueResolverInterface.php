<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface for value resolver plugins.
 */
interface EuOssCatalogueResolverInterface extends PluginInspectionInterface, ConfigurableInterface {

  /**
   * Resolves a value imported by a provider.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution $fetchedSolution
   *   Decoded content of a single business solution.
   * @param string $fieldName
   *   The Drupal field name.
   *
   * @return mixed
   *   The field resolved value.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\UnresolvableValueException
   *   If the value cannot be resolved.
   */
  public function resolve(EuOssCatalogueFetchedSolution $fetchedSolution, string $fieldName): mixed;

}
