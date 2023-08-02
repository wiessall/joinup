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

  /**
   * Downloads a file from the repository.
   *
   * @param string $filePath
   *   The file path relative to repository root directory.
   * @param \Drupal\eu_oss_catalogue\HostingPlatformResultInterface $result
   *   The code hosting platform result object.
   *
   * @return string|null
   *   The file contents or NULL if file doesn't exist.
   */
  public function downloadFile(string $filePath, HostingPlatformResultInterface $result): ?string;

}
