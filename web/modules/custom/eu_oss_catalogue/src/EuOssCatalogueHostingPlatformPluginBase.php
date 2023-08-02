<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Plugin\PluginBase;

/**
 * Interface for code hosting platform plugins.
 */
abstract class EuOssCatalogueHostingPlatformPluginBase extends PluginBase implements EuOssCatalogueHostingPlatformInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

}
