<?php

declare(strict_types=1);

namespace Joinup;

use Drush\TestTraits\DrushTestTrait;

/**
 * Class to detect the test mode.
 */
enum TestMode: string {

  case PhpUnit = 'phpunit';
  case Behat = 'behat';
  case Playwright = 'playwright';

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
  public static function current(): ?self {
    $value = \Drupal::config(self::CONFIG_NAME)->get('test_mode');
    return $value ? self::tryFrom($value) : NULL;
  }

  /**
   * Set the test mode.
   */
  public static function set(TestMode $mode): void {
    (new class() {
      use DrushTestTrait;
    })->drush('config:set', [self::CONFIG_NAME, 'test_mode', $mode->value], ['yes' => NULL]);
  }

  /**
   * Clear/unset the test mode.
   */
  public static function clear(): void {
    (new class() {
      use DrushTestTrait;
    })->drush('config:set', [self::CONFIG_NAME, 'test_mode', 'NULL'], ['yes' => NULL]);
  }

}
