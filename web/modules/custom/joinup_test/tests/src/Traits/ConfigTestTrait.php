<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\Traits;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Config reusable testing helper methods.
 */
trait ConfigTestTrait {

  /**
   * Config factory under test.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Keep track of any config that was changed, so they can easily be reverted.
   *
   * @var array
   */
  protected array $originalConfig = [];

  /**
   * Returns config sync configuration data.
   *
   * @param string $config_name
   *   The config to be retrieved from config sync directory.
   *
   * @return array
   *   Structured array with the configuration data.
   *
   * @throws \InvalidArgumentException
   *   If the passed configuration name is invalid.
   */
  protected function getConfigData(string $config_name): array {
    $config_sync_dir = realpath($this->root . '/../config/sync');
    $file_path = "{$config_sync_dir}/{$config_name}.yml";
    if (!file_exists($file_path) || !is_readable($file_path)) {
      throw new \InvalidArgumentException("File {$file_path} doesn't exist or is not readable.");
    }
    if (!$data = Yaml::decode(file_get_contents($file_path))) {
      throw new \InvalidArgumentException("File {$file_path} doesn't have a valid YAML syntax.");
    }
    return $data;
  }

  /**
   * Imports configs from config sync storage.
   *
   * @param string[] $config_names
   *   A list of config names to be imported.
   */
  protected function importConfigs(array $config_names): void {
    $config_factory = \Drupal::configFactory();
    foreach ($config_names as $config_name) {
      $config_data = $this->getConfigData($config_name);
      $config_factory->getEditable($config_name)->setData($config_data)->save();
    }
  }

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
