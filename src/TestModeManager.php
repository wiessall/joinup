<?php

declare(strict_types=1);

namespace Joinup;

use Drush\TestTraits\DrushTestTrait;

/**
 * Manages the test mode state for automated tests.
 */
final class TestModeManager {

  use DrushTestTrait;

  public const string CONFIG_NAME = 'joinup_test.settings';

  /**
   * Whether the environment is under test.
   */
  public static function isActive(): bool {
    return self::current() !== NULL;
  }

  /**
   * Get the current test mode.
   */
  public static function current(): ?TestMode {
    $value = \Drupal::config(self::CONFIG_NAME)->get('test_mode');
    return $value ? TestMode::tryFrom($value) : NULL;
  }

  /**
   * Set the test mode.
   */
  public static function set(TestMode $mode): void {
    (new self())->drush('config:set', [self::CONFIG_NAME, 'test_mode', $mode->value], ['yes' => NULL]);
  }

  /**
   * Clear/unset the test mode.
   */
  public static function clear(): void {
    (new self())->drush('config:set', [self::CONFIG_NAME, 'test_mode', 'NULL'], ['yes' => NULL]);
  }

}
