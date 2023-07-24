<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginBase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Base plugin for EU OSS Catalogue provider plugins.
 */
abstract class EuOssCatalogueProviderPluginBase extends PluginBase implements EuOssCatalogueProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alterSolution(OssSolutionInterface $solution, EuOssCatalogueFetchedSolution $fetchedSolution): void {
  }

}
