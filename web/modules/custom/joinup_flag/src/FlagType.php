<?php

declare(strict_types=1);

namespace Drupal\joinup_flag;

/**
 * Flag type.
 */
enum FlagType: string {

  case Like = 'like';
  case Bookmark = 'bookmark';

  /**
   * Gets an associative array.
   *
   * @return array
   *   Associative array
   */
  public static function toAssociativeArray(): array {
    $array = [];

    foreach (self::cases() as $case) {
      $array[$case->value] = $case->name;
    }

    return $array;
  }

}
