<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Catalogue type.
 */
enum CatalogueType: string {

  case European = 'european';
  case Local = 'local';
  case Municipal = 'municipal';
  case National = 'national';
  case Regional = 'regional';

  /**
   * Gets catalogue type label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The catalogue type label.
   */
  public function getLabel(): TranslatableMarkup {
    return match ($this) {
      self::European => new TranslatableMarkup('European', [], ['context' => 'eu_oss_catalogue']),
      self::Local => new TranslatableMarkup('Local', [], ['context' => 'eu_oss_catalogue']),
      self::Municipal => new TranslatableMarkup('Municipal', [], ['context' => 'eu_oss_catalogue']),
      self::National => new TranslatableMarkup('National', [], ['context' => 'eu_oss_catalogue']),
      self::Regional => new TranslatableMarkup('Regional', [], ['context' => 'eu_oss_catalogue']),
    };
  }

  /**
   * Get all valid choices.
   *
   * @return string[]
   *   All valid choices.
   */
  public static function validChoices(): array {
    return array_map(fn($case) => $case->value, CatalogueType::cases());
  }

}
