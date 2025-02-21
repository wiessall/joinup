<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Attribute;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Catalogue type.
 */
enum CatalogueType: string {

  case European = 'european';
  case National = 'national';
  case Regional = 'regional';
  case Municipal = 'municipal';
  case Local = 'local';

  /**
   * Gets catalogue type label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The catalogue type label.
   */
  public function getLabel(): TranslatableMarkup {
    return match ($this) {
      CatalogueType::European => new TranslatableMarkup('European', [], ['context' => 'eu_oss_catalogue']),
      CatalogueType::National => new TranslatableMarkup('National', [], ['context' => 'eu_oss_catalogue']),
      CatalogueType::Regional => new TranslatableMarkup('Regional', [], ['context' => 'eu_oss_catalogue']),
      CatalogueType::Municipal => new TranslatableMarkup('Municipal', [], ['context' => 'eu_oss_catalogue']),
      CatalogueType::Local => new TranslatableMarkup('Local', [], ['context' => 'eu_oss_catalogue']),
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
