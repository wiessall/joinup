<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\Traits;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Config reusable testing helper methods.
 */
trait ConfigTestTrait {

  /**
   * Config factory under test.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Keep track of any config that was changed, so they can easily be reverted.
   *
   * @var array
   */
  protected array $originalConfig = [];

  /**
   * Sets a value in a configuration object.
   *
   * @param string $name
   *   The name of the configuration object.
   * @param string $key
   *   Identifier to store value in configuration.
   * @param mixed $value
   *   Value to associate with identifier.
   */
  protected function overrideConfig(string $name, string $key, mixed $value): void {
    $config = $this->getConfigFactory()->getEditable($name);
    $original = $config->get($key);
    $config->set($key, $value)->save();
    if (!array_key_exists($name, $this->originalConfig)) {
      $this->originalConfig[$name][$key] = $original;
      return;
    }

    if (!array_key_exists($key, $this->originalConfig[$name])) {
      $this->originalConfig[$name][$key] = $original;
    }
  }

  /**
   * Restores original config.
   */
  protected function restoreOverriddenConfig(): void {
    if (!$this->originalConfig) {
      // Nothing to restore.
      return;
    }

    foreach ($this->originalConfig as $name => $key_value) {
      foreach ($key_value as $key => $value) {
        $this->getConfigFactory()->getEditable($name)->set($key, $value)->save();
      }
    }
    $this->originalConfig = [];
  }

  /**
   * Get the config factory.
   */
  protected function getConfigFactory(): ConfigFactoryInterface {
    if (!isset($this->configFactory)) {
      $this->configFactory = $this->container->get('config.factory');
    }
    return $this->configFactory;
  }

}
