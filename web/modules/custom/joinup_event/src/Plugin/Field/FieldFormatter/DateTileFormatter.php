<?php

declare(strict_types=1);

namespace Drupal\joinup_event\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\datetime_range\DateTimeRangeDisplayOptions;
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
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $separator = $this->getSetting('separator');

    foreach ($items as $delta => $item) {
      if (empty($item->start_date) && empty($item->end_date)) {
        continue;
      }

      /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
      $start_date = $item->start_date;
      /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
      $end_date = $item->end_date;

      if ($start_date->format('Y-m-d') === $end_date->format('Y-m-d')) {
        $elements[$delta] = $this->buildTileDate($start_date);
        continue;
      }

      if ($start_date->format('Y-m') === $end_date->format('Y-m')) {
        $elements[$delta] = $this->buildTileDate($start_date, $end_date);
        continue;
      }

      if ($this->startDateIsDisplayed()) {
        $elements[$delta][DateTimeRangeDisplayOptions::StartDate->value] = $this->buildTileDate($start_date);
      }
      if ($this->startDateIsDisplayed() && $this->endDateIsDisplayed()) {
        $elements[$delta]['separator'] = ['#plain_text' => ' ' . $separator . ' '];
      }
      if ($this->endDateIsDisplayed()) {
        $elements[$delta][DateTimeRangeDisplayOptions::EndDate->value] = $this->buildTileDate($end_date);
      }
    }

    return $elements;
  }

  /**
   * Builds the date tile render array.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $end_date
   *   The end date (optional, from the same month and year).
   *
   * @return array
   *   Render array for the date tile.
   */
  protected function buildTileDate(DrupalDateTime $start_date, ?DrupalDateTime $end_date = NULL): array {
    $this->setTimeZone($start_date);
    if ($end_date) {
      $this->setTimeZone($end_date);
    }

    return [
      '#theme' => 'joinup_event_date_tile',
      '#start_date' => $start_date,
      '#end_date' => $end_date,
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

}
