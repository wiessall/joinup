<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldFormatter\DateTimeFormatterBase;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;

/**
 * Plugin implementation of the 'Condensed' formatter for 'daterange' fields.
 *
 * @FieldFormatter(
 *   id = "daterange_condensed",
 *   label = @Translation("Condensed date range"),
 *   field_types = {
 *     "daterange"
 *   }
 * )
 */
class CondensedDateRangeFormatter extends DateTimeFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'separator' => 'to',
      'default_format' => 'd m Y',
      'day_diff_format' => 'd',
      'month_diff_format' => 'd F',
      'year_diff_format' => 'd F Y',
      'plain_text' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date separator'),
      '#description' => $this->t('The string to separate the start and end dates.'),
      '#default_value' => $this->getSetting('separator'),
    ];

    $form['default_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default format'),
      '#description' => $this->t('Format to use when the two dates are equal or completely different.'),
      '#default_value' => $this->getSetting('default_format'),
    ];

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

    $form['plain_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render as plain text'),
      '#description' => $this->t('Omits the ISO date attributes.'),
      '#default_value' => $this->getSetting('plain_text'),
    ];

    $form['help_text'] = [
      '#markup' => $this->t('See <a href="http://php.net/manual/function.date.php" target="_blank">the documentation for PHP date formats</a>.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    if ($separator = $this->getSetting('separator')) {
      $summary[] = $this->t('Separator: %separator', ['%separator' => $separator]);
    }

    $plain_text = $this->getSetting('plain_text');
    $summary[] = $this->t('Plain text: %value', ['%value' => $plain_text ? $this->t('yes') : $this->t('no')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $separator = $this->getSetting('separator');
    $plain_text = $this->getSetting('plain_text');

    foreach ($items as $delta => $item) {
      if (!empty($item->start_date) && !empty($item->end_date)) {
        /** @var \Drupal\Core\Datetime\DrupalDateTime $start_date */
        $start_date = $item->start_date;
        /** @var \Drupal\Core\Datetime\DrupalDateTime $end_date */
        $end_date = $item->end_date;

        // If the two dates formatted with the default format are the same,
        // render only one.
        if ($this->formatDate($start_date) === $this->formatDate($end_date)) {
          $elements[$delta] = $plain_text
            ? $this->buildDate($start_date)
            : $this->buildDateWithIsoAttribute($start_date);
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
          $format = $this->getSetting('default_format');
        }

        $elements[$delta] = [
          'start_date' => $plain_text ? $this->buildDate($start_date, $format) : $this->buildDateWithIsoAttribute($start_date, $format),
          'separator' => ['#plain_text' => ' ' . $separator . ' '],
          'end_date' => $plain_text ? $this->buildDate($end_date) : $this->buildDateWithIsoAttribute($end_date),
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatDate($date, $format = NULL): string {
    $format = $format ?: $this->getSetting('default_format');
    $timezone = $this->getSetting('timezone_override');
    return $this->dateFormatter->format($date->getTimestamp(), 'custom', $format, $timezone != '' ? $timezone : NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDate(DrupalDateTime $date, ?string $format = NULL): array {
    $this->setTimeZone($date);
    return [
      '#markup' => $this->formatDate($date, $format),
      '#cache' => [
        'contexts' => [
          'timezone',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDateWithIsoAttribute(DrupalDateTime $date, ?string $format = NULL): array {
    $format = $format ?: $this->getSetting('year_diff_format');

    if ($this->getFieldSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
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
