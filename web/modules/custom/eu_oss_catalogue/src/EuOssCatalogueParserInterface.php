<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Provides an interface for parser plugins.
 */
interface EuOssCatalogueParserInterface extends PluginInspectionInterface {

  /**
   * Parses fetched data and saves it as an OssSolution node.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project object.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface
   *   The new or updates OSS Solution node. If NULL is returned, parse failed.
   */
  public function parse(Project $project, EuOssCatalogueProviderInterface $provider): OssSolutionInterface;

}
