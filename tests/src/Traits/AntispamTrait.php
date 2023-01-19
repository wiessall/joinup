<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Site\Settings;

/**
 * Reusable code that allows switching Antibot & Honeypot functionality on/off.
 */
trait AntispamTrait {

  use ConfigReadOnlyTrait;

  /**
   * The current list of form IDs protected by Antibot.
   *
   * @var string[]
   */
  protected static array $currentAntibotFormIds;

  /**
   * Disables the Antibot & Honeypot functionality.
   */
  protected static function disableAntispam(): void {
    // Workaround Behat tests static cache persistence issue. Even all forms are
    // tagged with 'config:antibot.settings' cache tag and saving Antibot
    // settings will call the cache tags invalidator, as Behat tests are running
    // in a single, long requests, the cache tags invalidator checksum service
    // is statically caching the tags. We have to reset the service internal
    // cache prior saving the settings, so that the subsequent cache
    // invalidation to be in effect. Note that this is not needed, in a real
    // scenario, where the cache tags invalidator checksum service internal
    // cache is cleared at the end of each request.
    \Drupal::service('cache_tags.invalidator.checksum')->reset();

    // Unprotect all forms for the scope of this test. Antibot is blocking all
    // form submissions when javascript is disabled. As most of the tests are
    // running without javascript, we disable Antibot for all forms during the
    // tests.
    static::toggleModule('uninstall', ['antibot', 'honeypot']);
  }

  /**
   * Restores the Antibot & Honeypot functionality.
   */
  protected static function restoreAntispam(): void {
    static::toggleModule('install', ['antibot', 'honeypot']);
    static::restoreAntispamConfigs();
  }

  /**
   * Restores the Antibot & Honeypot configs.
   */
  protected static function restoreAntispamConfigs(): void {
    static::bypassReadOnlyConfig();
    // Import YAML config from an arbitrary file.
    $config_path = DRUPAL_ROOT . '/../config/sync';
    $source = new FileStorage($config_path);
    $config_storage = \Drupal::service('config.storage');
    $config_storage->write('honeypot.settings', $source->read('honeypot.settings'));
    $config_storage->write('antibot.settings', $source->read('antibot.settings'));
    static::restoreReadOnlyConfig();
  }

  /**
   * Remove Antibot & Honeypot configs.
   */
  protected static function removeAntispamConfigs(): void {
    static::bypassReadOnlyConfig();
    $config = \Drupal::service('config.factory');
    // Save the current form IDs protected by Antibot.
    $antibot_settings = $config->getEditable('antibot.settings');
    static::$currentAntibotFormIds = $antibot_settings->get('form_ids');
    $antibot_settings->set('form_ids', [])->save();
    // Disable time_limit protection in form protected by Honeypot.
    $honeypot = $config->getEditable('honeypot.settings');
    $honeypot->set('time_limit', 0)->save();
    static::restoreReadOnlyConfig();
  }

  /**
   * Installs/uninstalls a module in tests.
   *
   * @param string $method
   *   Either 'install' or 'uninstall'.
   * @param array $module_name
   *   The modules to be installed/uninstalled.
   */
  protected static function toggleModule(string $method, array $module_name): void {
    // Ensure that test modules are also discoverable.
    $settings = ['extension_discovery_scan_tests' => TRUE] + Settings::getAll();
    new Settings($settings);

    static::bypassReadOnlyConfig();
    \Drupal::service('module_installer')->$method($module_name);
    static::restoreReadOnlyConfig();
  }

}
