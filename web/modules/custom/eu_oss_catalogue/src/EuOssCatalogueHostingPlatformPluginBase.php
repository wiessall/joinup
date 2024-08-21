<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for code hosting platform plugins.
 */
abstract class EuOssCatalogueHostingPlatformPluginBase extends PluginBase implements EuOssCatalogueHostingPlatformInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

}
