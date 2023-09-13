<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for EuOssCatalogueGitHostingApi plugins.
 */
interface EuOssCatalogueHostingPlatformInterface extends ConfigurableInterface {

  /**
   * Generates a list of repositories.
   *
   * @return iterable
   *   Array of \Drupal\eu_oss_catalogue\Model\HostingPlatformResult instances.
   */
  public function getProjects(?string $filePath = NULL): iterable;

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

}
