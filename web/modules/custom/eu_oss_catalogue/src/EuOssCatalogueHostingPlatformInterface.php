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
   *   Associative array of repositories, keyed by $namespace/$name and having
   *   \Drupal\eu_oss_catalogue\Model\HostingPlatformResult instances as values.
   */
  public function getProjects(?string $filePath = NULL): iterable;

  /**
   * Returns the label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getLabel(): TranslatableMarkup;

}
