<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Utils;

/**
 * Contains utility methods.
 */
class TestUtils {

  /**
   * Whether we are in test mode or not.
   */
  public static function isTestMode(): bool {
    return (bool) \Drupal::config('joinup_test.settings')->get('test_mode');
  }

}
