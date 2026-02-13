<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Defines the 'conditional_link' field widget.
 */
#[FieldWidget(
  id: 'conditional_link',
  label: new TranslatableMarkup('Conditional link'),
  field_types: ['link']
)]
final class ConditionalLinkWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'condition' => 'Add some links...',
      'default_option' => '0',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['condition'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Condition text'),
      '#default_value' => $this->getSetting('condition'),
      '#required' => TRUE,
      '#description' => $this->t('Shown above the widget as a radio button list of Yes/No options.'),
    ];

    $elements['default_option'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Default option'),
      '#description' => $this->t('Default option for this setting.'),
      '#default_value' => $this->getSetting('default_option'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $summary[] = $this->t('condition: @condition', ['@condition' => $this->getSetting('condition') ?: '']);
    $summary[] = $this->t(
      'Default option: @default_option',
      ['@default_option' => $this->getSetting('default_option')]
    );

    return $summary;
  }

  /**
   * Build the complete widget for this field (all deltas).
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL): array {
    // Let the parent build the normal widget structure first.
    $element = parent::form($items, $form, $form_state, $get_delta);

    // Determine if the field currently has any value at all.
    $hasAnyValue = FALSE;
    foreach ($items as $item) {
      if (!$item->isEmpty()) {
        $hasAnyValue = TRUE;
        break;
      }
    }

    $condition = $this->getSetting('condition');
    $element['add_link'] = [
      '#type' => 'checkbox',
      '#title' => $condition,
      '#default_value' => $hasAnyValue ? '1' : $this->getSetting('default_option'),
      '#weight' => -10,
      '#attributes' => ['class' => ['link-with-condition-toggle']],
    ];

    // Apply #states visibility so that the entire widget (all deltas) is
    // hidden unless the checkbox is checked.
    if (isset($element['widget']) && is_array($element['widget'])) {
      if (!isset($element['widget']['#states'])) {
        $element['widget']['#states'] = [];
      }
      $element['widget']['#states']['visible'][':input.link-with-condition-toggle'] = ['checked' => TRUE];
    }

    unset($element['widget']['#description']);
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * $values here are only the per-delta link values (no add_link),
   * so we read the global checkbox from $form_state directly.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    // Normalize link values first (uri/title/options handling).
    $values = parent::massageFormValues($values, $form, $form_state);

    // Retrieve the "add_link" checkbox from the full form state.
    $fieldInput = $form_state->getUserInput();
    // $fieldInput is expected to contain 'add_link' and 'widget'.
    $allowLinks = $fieldInput['add_link'] === '1';
    if (!$allowLinks) {
      foreach ($values as &$value) {
        $value['uri'] = NULL;
        if (array_key_exists('title', $value)) {
          $value['title'] = NULL;
        }
        if (array_key_exists('options', $value)) {
          $value['options'] = [];
        }
      }
      unset($value);
    }

    return $values;
  }

}
