<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'Vitality index chart' formatter.
 *
 * @FieldFormatter(
 *   id = "vitality_index_chart",
 *   label = @Translation("Vitality index chart"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class VitalityIndexChartFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'info' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['info'] = [
      '#title' => $this->t('Info'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('info'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {
      // We're only decoding to check validity.
      try {
        $vitalityIndex = Json::decode($item->value);
      }
      catch (\Throwable $exception) {
        continue;
      }

      $element[$delta] = [
        '#theme' => 'vitality_index_chart',
        '#info' => $this->getSetting('info'),
        '#range' => Json::encode($vitalityIndex),
      ];
    }

    return $element;
  }

}
