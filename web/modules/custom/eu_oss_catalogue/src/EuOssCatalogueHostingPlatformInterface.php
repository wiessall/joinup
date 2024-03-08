<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for EuOssCatalogueGitHostingApi plugins.
 */
interface EuOssCatalogueHostingPlatformInterface extends ConfigurableInterface {

  /**
   * Generates a list of project objects.
   *
   * @param string[] $requestedFiles
   *   (optional) Associative where with keys are file identifiers and values
   *   are file paths relative to the project's repository root. If provided,
   *   the default branch version of such files are downloaded and stored in
   *   each project object, if they exist. The array values should be unique.
   *
   * @return iterable
   *   Array of \Drupal\eu_oss_catalogue\Model\Project instances.
   *
   * @throws \Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException
   *   If any exception happens during fetch, e.g. the server is unavailable or
   *   request quota is exceeded.
   */
  public function getProjects(array $requestedFiles = []): iterable;

  /**
   * Returns the label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getLabel(): TranslatableMarkup;

  /**
   * Determines whether the plugin can parse the provided git URL.
   *
   * @param string $repositoryUrl
   *   The repository URL.
   *
   * @return bool
   *   Whether the plugin can parse the provided git URL.
   */
  public function isHostingPlatformForUrl(string $repositoryUrl): bool;

  /**
   * Returns the code hosting platform URL given a project URL.
   *
   * @param string $repositoryUrl
   *   The project repository URL.
   *
   * @return string|null
   *   The determined base URL of the code hosting platform or NULL if it
   *   cannot be determined.
   */
  public function getBaseFromRepoUrl(string $repositoryUrl): ?string;

  /**
   * Returns a file raw URL given a relative path or URL.
   *
   * @param string $url
   *   A file path relative to the repository root or a full URL.
   * @param string $projectUrl
   *   The project homepage URL.
   *
   * @return string|null
   *   The file raw URL or NULL if it cannot be resolved. If an arbitrary URL,
   *   external to repository is passed, will be returned as it is.
   */
  public function getFileRawUrl(string $url, string $projectUrl): ?string;

}
