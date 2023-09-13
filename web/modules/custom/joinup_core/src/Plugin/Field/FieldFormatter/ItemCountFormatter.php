<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formatter returning how main items the field has, e.g. '<n> items'.
 *
 * @FieldFormatter(
 *   id = "joinup_core_item_count",
 *   label = @Translation("Item count"),
 *   deriver = "Drupal\joinup_core\Plugin\Deriver\AllFieldTypeFormatterDeriver"
 * )
 */
class ItemCountFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'count_suffix_single' => '',
      'count_suffix_multiple' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return [
      [
        '#markup' => $this->formatPlural(
          $items->count(),
          '@count @suffix_singe',
          '@count @suffix_multiple',
          [
            '@count' => $items->count(),
            '@suffix_single' => $this->getSetting('count_suffix_single'),
            '@suffix_multiple' => $this->getSetting('count_suffix_multiple'),
          ]
        ),
      ],
    ];
  }

  /**
   * Returns a form to configure settings for the formatter.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   *
   * @return array
   *   The form elements for the formatter settings.
   */
  public function settingsForm(array $form, FormStateInterface $formState): array {
    $form = parent::settingsForm($form, $formState);
    $form['count_suffix_single'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix for single element'),
      '#default_value' => $this->getSetting('count_suffix_single'),
    ];
    $form['count_suffix_multiple'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Suffix for multiple elements'),
      '#default_value' => $this->getSetting('count_suffix_multiple'),
    ];

    $form['translation_warning'] = [
      '#markup' => $this->t('Please be aware that the above configurations must be translated manually, by creating a language override in the corresponding collection for the appropriate entity view display and changing the config keys there.'),
    ];

    return $form;
  }

}
