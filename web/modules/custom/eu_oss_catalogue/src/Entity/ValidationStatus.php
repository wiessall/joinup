<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

/**
 * Provides the possible values for validation status.
 */
enum ValidationStatus: string {

  case Valid = 'valid';
  case Invalid = 'invalid';

  /**
   * Returns the enum cases suitable for validation status.
   *
   * @return array<string,string>
   *   List of cases keyed by enum case value.
   */
  public static function getValues(): array {
    $values = [];
    foreach (self::cases() as $case) {
      $values[$case->value] = $case->name;
    }
    return $values;
  }

}
