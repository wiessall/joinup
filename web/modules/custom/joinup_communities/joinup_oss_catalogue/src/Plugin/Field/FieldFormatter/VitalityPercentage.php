<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\NumericFormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation 'joinup_oss_catalogue_vitality_percentage'.
 *
 * @FieldFormatter(
 *   id = "joinup_oss_catalogue_vitality_percentage",
 *   label = @Translation("Vitality percentage"),
 *   field_types = {
 *      "decimal",
 *      "float"
 *   }
 * )
 */
class VitalityPercentage extends NumericFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function numberFormat($number): string {
    return floor($number * 100) . '%';
  }

}
