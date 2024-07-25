<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\ConfigContext as DrupalExtensionConfigContext;
use Drupal\joinup\Traits\ConfigReadOnlyTrait;

/**
 * Behat step definitions to test config.
 */
class ConfigContext extends DrupalExtensionConfigContext {

  use ConfigReadOnlyTrait;

  /**
   * {@inheritdoc}
   */
  public function setConfig($name, $key, $value): void {
    static::bypassReadOnlyConfig();
    parent::setConfig($name, $key, $value);
    static::bypassReadOnlyConfig();
  }

  /**
   * {@inheritdoc}
   */
  public function cleanConfig(): void {
    if (!$this->config) {
      // Nothing to restore.
      return;
    }

    static::bypassReadOnlyConfig();
    parent::cleanConfig();
    static::bypassReadOnlyConfig();
  }

}
