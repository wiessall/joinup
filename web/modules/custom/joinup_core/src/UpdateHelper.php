<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\joinup_group\Entity\ShareableContentTrait;

/**
 * Helper for shared in meta entity related update.
 *
 * Created to calculates the share expiration timestamp as done in
 * ShareableContentTrait. Can be removed with the corresponding post update
 * functions.
 */
class UpdateHelper {

  use ShareableContentTrait {
    someMonthsLater as traitSomeMonthsLater;
  }

  public static function someMonthsLater(int $monthsToAdd, int $timestamp): int {
    try {
      return static::traitSomeMonthsLater($monthsToAdd, $timestamp);
    }
    catch (\Throwable $t) {
      $d = 1;
      throw $t;
    }
  }

}
