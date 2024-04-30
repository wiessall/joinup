<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\addtocal\Plugin\Field\FieldFormatter\AddtocalView;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;

/**
 * Plugin of the 'Condensed' formatter for 'daterange' and 'Add to Cal' fields.
 *
 * @FieldFormatter(
 *   id = "addtocall_daterange_condensed",
 *   label = @Translation("Add to Cal condensed date range"),
 *   field_types = {
 *     "daterange"
 *   }
 * )
 */
class CondensedAddToCallDateFormatter extends AddtocalView {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'day_diff_format' => 'd',
      'month_diff_format' => 'd F',
      'year_diff_format' => 'd F Y',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['day_diff_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Different days format'),
      '#description' => $this->t('Format to use when the start and end date span over a day.'),
      '#default_value' => $this->getSetting('day_diff_format'),
    ];

    $form['month_diff_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Different months format'),
      '#description' => $this->t('Format to use when the start and end date span over a new month.'),
      '#default_value' => $this->getSetting('month_diff_format'),
    ];

    $form['year_diff_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Different years format'),
      '#description' => $this->t('Format to use when the start and end date span over a new year.'),
      '#default_value' => $this->getSetting('year_diff_format'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    $separator = $this->getSetting('separator');

    foreach ($items as $delta => $item) {
      if (!empty($item->start_date) && !empty($item->end_date)) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
        $start_date = $item->start_date;
        /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
        $end_date = $item->end_date;

        // If the two dates formatted with the default format are the same,
        // render only one.
        if ($this->formatDate($start_date) === $this->formatDate($end_date)) {
          $elements[$delta]['start_date'] = $this->buildDateWithIsoAttribute($start_date);
          unset($elements[$delta]['separator']);
          unset($elements[$delta]['end_date']);
          continue;
        }

        // Render the start date depending on the range between the two.
        if ($this->formatDate($start_date, 'Y') !== $this->formatDate($end_date, 'Y')) {
          $format = $this->getSetting('year_diff_format');
        }
        elseif ($this->formatDate($start_date, 'm') !== $this->formatDate($end_date, 'm')) {
          $format = $this->getSetting('month_diff_format');
        }
        elseif ($this->formatDate($start_date, 'd') !== $this->formatDate($end_date, 'd')) {
          $format = $this->getSetting('day_diff_format');
        }
        else {
          $format = $this->getSetting('date_format');
        }

        $elements[$delta]['start_date'] = $this->buildDateWithIsoAttribute($start_date, $format);
        $elements[$delta]['separator'] = ['#plain_text' => ' ' . $separator . ' '];
        $elements[$delta]['end_date'] = $this->buildDateWithIsoAttribute($end_date);

        $context = [
          'items' => $items,
          'langcode' => $langcode,
          'delta' => $delta,
        ];

        $this->moduleHandler->alter('addtocal_links', $links, $context);
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date, ?string $format = NULL): string {
    $format = $format ? $format : $this->getSetting('date_format');
    $timezone = $this->getSetting('timezone_override');
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone != '' ? $timezone : NULL);
  }

  /**
   * Creates a render array from a date object with ISO date attribute.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   A date object.
   * @param string|null $format
   *   The format of the displayed date. NULL to use the default one.
   *
   * @return array
   *   A render array.
   */
  protected function buildDateWithIsoAttribute(DrupalDateTime $date, ?string $format = NULL): array {
    $format = $format ? $format : $this->getSetting('year_diff_format');

    if ($this->getFieldSetting('datetime_type') == DateTimeItem::DATETIME_TYPE_DATE) {
      // A date without time will pick up the current time, use the default.
      $date->setDefaultDateTime();
    }

    // Create the ISO date in Universal Time.
    $iso_date = $date->format("Y-m-d\TH:i:s") . 'Z';

    $this->setTimeZone($date);

    return [
      '#theme' => 'time',
      '#text' => $this->formatDate($date, $format),
      '#html' => FALSE,
      '#attributes' => [
        'datetime' => $iso_date,
      ],
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

}
