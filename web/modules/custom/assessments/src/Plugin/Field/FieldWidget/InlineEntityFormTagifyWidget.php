<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;
use Drupal\tagify\Element\EntityAutocompleteTagify;

/**
 * Inline entity form widget with Tagify autocomplete for existing entities.
 *
 * This widget extends the IEF Complex widget to use Tagify for the "Add
 * existing" autocomplete, providing a better UX while maintaining the ability
 * to create new entities inline.
 */
#[FieldWidget(
  id: 'inline_entity_form_tagify',
  label: new TranslatableMarkup('Inline entity form - Tagify'),
  field_types: ['entity_reference'],
  multiple_values: TRUE,
)]
class InlineEntityFormTagifyWidget extends InlineEntityFormComplex {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'suggestions_dropdown' => 1,
      'match_limit' => 10,
      'show_entity_id' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);

    $element['suggestions_dropdown'] = [
      '#type' => 'radios',
      '#title' => $this->t('Suggestions dropdown'),
      '#default_value' => $this->getSetting('suggestions_dropdown'),
      '#options' => [
        0 => $this->t('On click'),
        1 => $this->t('When 1 character is typed'),
      ],
      '#description' => $this->t('Select the method used to show suggestions dropdown.'),
    ];

    $element['match_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $this->getSetting('match_limit'),
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use 0 to remove the limit.'),
    ];

    $element['show_entity_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include entity ID'),
      '#default_value' => $this->getSetting('show_entity_id'),
      '#description' => $this->t('Include the entity ID within the tag.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $dropdown_options = [
      0 => $this->t('On click'),
      1 => $this->t('When 1 character is typed'),
    ];
    $summary[] = $this->t('Tagify dropdown: @option', [
      '@option' => $dropdown_options[$this->getSetting('suggestions_dropdown')],
    ]);

    $limit = $this->getSetting('match_limit') ?: $this->t('unlimited');
    $summary[] = $this->t('Suggestion limit: @limit', ['@limit' => $limit]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Check if the "Add existing" form is open.
    $open_form = $form_state->get(['inline_entity_form', $this->getIefId(), 'form']);

    if ($open_form === 'ief_add_existing' && isset($element['form']['entity_id'])) {
      // Store widget settings for the process callback.
      $element['form']['#ief_tagify_settings'] = [
        'suggestions_dropdown' => $this->getSetting('suggestions_dropdown'),
        'match_limit' => $this->getSetting('match_limit'),
        'show_entity_id' => $this->getSetting('show_entity_id'),
        'match_operator' => $this->getSetting('match_operator'),
      ];

      // Add process callback to replace autocomplete with Tagify.
      $element['form']['#process'][] = [static::class, 'processTagifyAutocomplete'];
    }

    return $element;
  }

  /**
   * Process callback to replace entity_autocomplete with Tagify.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed form element.
   */
  public static function processTagifyAutocomplete(array $element, FormStateInterface $form_state, array &$complete_form): array {
    if (!isset($element['entity_id'])) {
      return $element;
    }

    $settings = $element['#ief_tagify_settings'] ?? [];

    // Get existing selection settings.
    $selection_settings = $element['entity_id']['#selection_settings'] ?? [];
    $selection_settings['match_operator'] = $settings['match_operator'] ?? 'CONTAINS';

    // Build a unique identifier for Tagify based on IEF id.
    $identifier = 'ief-tagify-' . ($element['#ief_id'] ?? 'default');

    // Replace the element type with Tagify and set all required properties.
    $element['entity_id']['#type'] = 'entity_autocomplete_tagify';
    $element['entity_id']['#selection_settings'] = $selection_settings;
    $element['entity_id']['#suggestions_dropdown'] = $settings['suggestions_dropdown'] ?? 1;
    $element['entity_id']['#max_items'] = $settings['match_limit'] ?? 10;
    $element['entity_id']['#match_operator'] = $settings['match_operator'] ?? 'CONTAINS';
    $element['entity_id']['#show_entity_id'] = $settings['show_entity_id'] ?? FALSE;
    $element['entity_id']['#cardinality'] = 1;
    // Must be 1 to show non-hierarchical items in dropdown.
    $element['entity_id']['#parent_selection'] = 1;
    $element['entity_id']['#identifier'] = $identifier;
    $element['entity_id']['#info_label'] = '';
    $element['entity_id']['#autocreate'] = NULL;

    // Add identifier as a CSS class - required by Tagify JS.
    if (!isset($element['entity_id']['#attributes']['class'])) {
      $element['entity_id']['#attributes']['class'] = [];
    }
    $element['entity_id']['#attributes']['class'][] = $identifier;
    $element['entity_id']['#attributes']['class'][] = 'tagify--limited';

    // Remove maxlength that might interfere with Tagify's JSON.
    unset($element['entity_id']['#maxlength']);

    // Manually call Tagify's element processor since changing #type in a
    // process callback doesn't trigger the new type's processors.
    $element['entity_id'] = EntityAutocompleteTagify::processEntityAutocompleteTagify(
      $element['entity_id'],
      $form_state,
      $complete_form
    );

    // Add value callback to convert Tagify JSON to entity ID before validation.
    $element['entity_id']['#value_callback'] = [static::class, 'valueCallback'];

    // Replace core EntityAutocomplete validation with our own simple validation.
    // EntityAutocomplete validation changes the value format which breaks IEF.
    $element['entity_id']['#element_validate'] = [
      [static::class, 'validateEntityExists'],
    ];

    // Set #tags to FALSE - IEF expects this property.
    $element['entity_id']['#tags'] = FALSE;

    // Attach library for z-index fix in modals.
    $element['entity_id']['#attached']['library'][] = 'assessments/ief_tagify';

    return $element;
  }

  /**
   * Value callback to convert Tagify JSON to "Label (ID)" format.
   *
   * Tagify returns values as JSON. IEF's extractFormValues expects the
   * "Label (ID)" format to extract the entity ID using
   * EntityAutocomplete::extractEntityIdFromAutocompleteInput().
   *
   * @param array $element
   *   The form element.
   * @param mixed $input
   *   The input value.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The value in "Label (ID)" format for IEF compatibility.
   */
  public static function valueCallback(array &$element, $input, FormStateInterface $form_state) {
    // If no input, return NULL.
    if ($input === FALSE) {
      return NULL;
    }

    // If input is a JSON string from Tagify, convert to "Label (ID)" format.
    if (!empty($input) && is_string($input)) {
      $data = json_decode($input, TRUE);
      if (is_array($data) && !empty($data[0])) {
        $item = $data[0];
        $entity_id = $item['entity_id'] ?? $item['value'] ?? NULL;
        $label = $item['label'] ?? 'Entity';

        if ($entity_id && is_numeric($entity_id)) {
          // Return in "Label (ID)" format for IEF's extractFormValues.
          return $label . ' (' . $entity_id . ')';
        }
      }
    }

    // Return input as-is if not JSON or already in correct format.
    return $input;
  }

  /**
   * Validation callback to verify the selected entity exists.
   *
   * This is a simple validation that checks if the entity exists without
   * changing the value format (unlike core EntityAutocomplete validation).
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateEntityExists(array &$element, FormStateInterface $form_state): void {
    $value = $element['#value'];

    if (empty($value)) {
      return;
    }

    // Value is in "Label (ID)" format from valueCallback.
    // Extract the entity ID using the same method IEF uses.
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($value);

    if ($entity_id === NULL) {
      $form_state->setError($element, t('Invalid entity selection.'));
      return;
    }

    // Check if the entity exists.
    $target_type = $element['#target_type'];
    $entity = \Drupal::entityTypeManager()->getStorage($target_type)->load($entity_id);

    if (!$entity) {
      $form_state->setError($element, t('The selected entity does not exist.'));
      return;
    }

    // Set the value to just the entity ID for IEF's reference form validation
    // and submit handlers which expect a numeric ID.
    $form_state->setValueForElement($element, $entity_id);
  }

}
