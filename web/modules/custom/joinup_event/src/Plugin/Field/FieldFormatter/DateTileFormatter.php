<?php

declare(strict_types=1);

namespace Drupal\joinup_event\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\Plugin\Field\FieldFormatter\DateRangePlainFormatter;

/**
 * Plugin implementation of the 'Date tile' formatter for 'daterange' fields.
 */
#[FieldFormatter(
  id: 'date_tile',
  label: new TranslatableMarkup('Date tile'),
  field_types: [
    'daterange',
  ],
)]
class DateTileFormatter extends DateRangePlainFormatter {

  /**
   * {@inheritdoc}
   */
  protected function buildDate(DrupalDateTime $date): array {
    $this->setTimeZone($date);

    return [
      '#theme' => 'joinup_event_date_tile',
      '#date' => $date,
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

}
