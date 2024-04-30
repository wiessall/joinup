<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\Project;

/**
 * Interface for EuOssCatalogProvider plugins.
 */
interface EuOssCatalogueProviderInterface extends PluginInspectionInterface {

  /**
   * Fetches solutions from provider.
   *
   * For performance reasons, when dealing with large sets of data, this method
   * should return a limited subset. The caller is responsible to run this
   * method as many times is needed as long as the iterable return is valid.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Project[]
   *   Indexed array of project value objects.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
   *   If any exception happens during fetch, e.g. the server is unavailable or
   *   request quota is exceeded.
   */
  public function fetch(): iterable;

  /**
   * Alters the solution created by the parser before is saved.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution node entity.
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   */
  public function alterSolution(OssSolutionInterface $solution, Project $project): void;

  /**
   * Returns the parser associated to this provider.
   *
   * @return \Drupal\eu_oss_catalogue\EuOssCatalogueParserInterface
   *   The parser associated to this provider.
   */
  public function getParser(): EuOssCatalogueParserInterface;

}
