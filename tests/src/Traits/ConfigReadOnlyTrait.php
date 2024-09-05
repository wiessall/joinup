<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Core\Site\Settings;

/**
 * Methods to help circumvent read only config during testing.
 *
 * When the Config Read Only module is enabled it is no longer possible to
 * change configuration, but sometimes this is required during testing, for
 * example to enable temporary test services, or to test different variations of
 * a certain feature.
 */
trait ConfigReadOnlyTrait {

  /**
   * Temporarily disables read only configuration.
   *
   * Make sure to call restoreReadOnlyConfig() after making the necessary config
   * changes.
   */
  public static function bypassReadOnlyConfig(): void {
    static::checkConfigReadOnlyKillSwitch();
    \Drupal::getContainer()->get('joinup_core.switcher')->disable('config-readonly');
    // Ensure the new value also for the current request.
    new Settings(['config_readonly' => FALSE] + Settings::getAll());
  }

  /**
   * Restores the read only configuration functionality if available.
   */
  public static function restoreReadOnlyConfig(): void {
    \Drupal::getContainer()->get('joinup_core.switcher')->enable('config-readonly');
    // Ensure the new value also for the current request.
    new Settings([
      'config_readonly' => getenv('CONFIG_READONLY') !== 'false',
    ] + Settings::getAll());
  }

  /**
   * Checks if the `$settings['config_readonly']` kill-switch exists.
   *
   * @throws \Exception
   *   If the kill-switch is missed.
   */
  protected static function checkConfigReadOnlyKillSwitch(): void {
    /** @var \Drupal\Core\DrupalKernelInterface $kernel */
    $kernel = \Drupal::service('kernel');
    $site_path = $kernel->getSitePath();
    $needle = "\$settings['config_readonly'] = getenv('CONFIG_READONLY') !== 'false' && !file_exists(getenv('DRUPAL_PRIVATE_FILE_SYSTEM') . '/killswitch/disable-config-readonly');";
    $settingsPhp = file_get_contents("{$site_path}/settings.php");
    if (!str_contains($settingsPhp, $needle)) {
      throw new \Exception("The following line is missing from web/sites/default/settings.php\n$needle");
    }
  }

}
