<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\ConfigurableInterface;

/**
 * Interface for EuOssCatalogueGitHostingApi plugins.
 */
interface EuOssCatalogueHostingPlatformInterface extends ConfigurableInterface {

  /**
   * Generates a list of repositories.
   *
   * @return iterable
   *   Associative array of repositories, keyed by $vendor/$name and having the
   *   default branch as values.
   */
  public function getRepositories(): iterable;

}
