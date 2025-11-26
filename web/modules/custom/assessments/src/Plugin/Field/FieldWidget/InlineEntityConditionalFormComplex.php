<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\Field\FieldWidget;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for entity references with conditional field visibility support.
 *
 * Extends the Inline Entity Form Complex widget to provide dynamic field
 * visibility based on configurable rules. Fields can be shown or hidden
 * based on the values of other fields in the referenced entity form.
 *
 * @example
 * Example YAML configuration:
 * @code
 * - trigger_field: field_type
 *   target_field: field_details
 *   hide_when_values:
 *     - type_a
 *     - type_b
 *   clear_when_hidden: true
 * - trigger_field: field_status
 *   target_field: field_reason
 *   show_when_values:
 *     - rejected
 * @endcode
 */
#[FieldWidget(
  id: 'inline_entity_conditional_form_complex',
  label: new TranslatableMarkup('Inline entity form with conditional fields'),
  field_types: ['entity_reference'],
)]
final class InlineEntityConditionalFormComplex extends InlineEntityFormComplex {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    ModuleHandlerInterface $module_handler,
    $selection_manager,
    protected Yaml $serialization,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings,
      $entity_type_bundle_info,
      $entity_type_manager,
      $entity_display_repository,
      $module_handler,
      $selection_manager
    );
    $this->logger = $loggerFactory->get('assessments');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('module_handler'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('serialization.yaml'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();
    $settings['allow_new'] = TRUE;
    $settings['allow_existing'] = TRUE;
    $settings['conditional_fields'] = [];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);

    $element['conditional_fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Conditional field settings'),
      '#description' => $this->t('Configure conditional visibility rules for fields in the inline entity form. Control when fields are shown or hidden based on the value of other fields.'),
      '#open' => FALSE,
    ];

    $element['conditional_fields']['rules'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Conditional rules (YAML)'),
      '#default_value' => $this->getSetting('conditional_fields') ? $this->formatRulesForDisplay($this->getSetting('conditional_fields')) : '',
      '#description' => $this->t('Define rules in YAML format. Each rule requires trigger_field and target_field. Optional properties: hide_when_values, show_when_values, clear_when_hidden (default: true).'),
      '#rows' => 15,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    $conditionalFields = $this->getSetting('conditional_fields');
    if (!empty($conditionalFields)) {
      $summary[] = $this->t('Conditional fields: @count rule(s) configured', [
        '@count' => count($conditionalFields),
      ]);
    }
    else {
      $summary[] = $this->t('No conditional field rules configured');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state): array {
    $elements = parent::formMultipleElements($items, $form, $form_state);

    $rules = $this->getConditionalRules();
    if (empty($rules)) {
      return $elements;
    }

    $elements['#conditional_rules'] = $rules;
    $elements['#process'][] = [static::class, 'processWidget'];
    $elements['#after_build'][] = [static::class, 'afterBuild'];

    return $elements;
  }

  /**
   * Process callback to attach conditional logic to inline entity forms.
   *
   * This callback iterates through field deltas to locate inline entity form
   * structures and attaches a subsequent process callback that will apply
   * conditional field visibility rules after IEF has built the entity form.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed form element.
   */
  public static function processWidget(array $element, FormStateInterface $form_state, array &$complete_form): array {
    $rules = $element['#conditional_rules'] ?? [];

    if (empty($rules)) {
      return $element;
    }

    foreach ($element as $key => &$child) {
      if (!is_numeric($key) || !is_array($child)) {
        continue;
      }

      // Handle the "add new" form structure.
      if (isset($child['form']['inline_entity_form']['#process'])) {
        $child['form']['inline_entity_form']['#conditional_rules'] = $rules;
        $child['form']['inline_entity_form']['#process'][] = [
          static::class,
          'processInlineForm',
        ];
      }

      // Handle the "edit existing" form structure.
      if (isset($child['entities']) && is_array($child['entities'])) {
        foreach ($child['entities'] as &$entity_element) {
          if (isset($entity_element['form']['inline_entity_form']['#process'])) {
            $entity_element['form']['inline_entity_form']['#conditional_rules'] = $rules;
            $entity_element['form']['inline_entity_form']['#process'][] = [
              static::class,
              'processInlineForm',
            ];
          }
        }
        unset($entity_element);
      }
    }
    unset($child);

    return $element;
  }

  /**
   * Process callback for inline entity forms to apply conditional states.
   *
   * This callback executes after Inline Entity Form's processEntityForm(),
   * ensuring that all entity form fields have been built. It applies Drupal's
   * #states API to implement conditional field visibility.
   *
   * @param array $element
   *   The inline entity form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed inline form element with states applied.
   */
  public static function processInlineForm(array $element, FormStateInterface $form_state, array &$complete_form): array {
    $rules = $element['#conditional_rules'] ?? [];

    if (empty($rules)) {
      return $element;
    }

    static::applyConditionalLogic($element, $rules);

    return $element;
  }

  /**
   * After build callback to ensure required JavaScript libraries are loaded.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The element with attached libraries.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    $element['#attached']['library'][] = 'core/drupal.states';
    return $element;
  }

  /**
   * Applies conditional visibility rules to inline entity form fields.
   *
   * Iterates through configured rules and applies Drupal #states to target
   * fields based on trigger field values. Supports visibility and invisibility
   * states.
   *
   * @param array $inlineForm
   *   The inline entity form array, passed by reference.
   * @param array $rules
   *   Array of conditional rules, each containing:
   *   - trigger_field: Field name that triggers the condition.
   *   - target_field: Field name to be shown/hidden.
   *   - hide_when_values: Array of trigger values that hide the target.
   *   - show_when_values: Array of trigger values that show the target.
   *   - clear_when_hidden: Boolean to clear target value when hidden.
   */
  protected static function applyConditionalLogic(array &$inlineForm, array $rules): void {
    foreach ($rules as $rule) {
      $triggerField = $rule['trigger_field'] ?? NULL;
      $targetField = $rule['target_field'] ?? NULL;
      $hideWhenValues = $rule['hide_when_values'] ?? [];
      $showWhenValues = $rule['show_when_values'] ?? [];

      if (!$triggerField || !$targetField) {
        continue;
      }

      if (!isset($inlineForm[$triggerField]) || !isset($inlineForm[$targetField])) {
        continue;
      }

      $triggerElement = &$inlineForm[$triggerField];
      $targetElement = &$inlineForm[$targetField];

      $triggerHasWidget = isset($triggerElement['widget']);
      $targetHasWidget = isset($targetElement['widget']);

      $triggerSelector = static::buildFieldSelector(
        $inlineForm,
        $triggerField,
        $triggerHasWidget
      );

      $states = static::buildStates(
        $triggerSelector,
        $hideWhenValues,
        $showWhenValues
      );

      if (!empty($states)) {
        if (!isset($targetElement['#states'])) {
          $targetElement['#states'] = [];
        }
        $targetElement['#states'] = array_merge_recursive(
          $targetElement['#states'],
          $states
        );

        if ($targetHasWidget && isset($targetElement['widget'])) {
          if (!isset($targetElement['widget']['#states'])) {
            $targetElement['widget']['#states'] = [];
          }
          $targetElement['widget']['#states'] = array_merge_recursive(
            $targetElement['widget']['#states'],
            $states
          );
        }
      }
    }
  }

  /**
   * Retrieves and parses conditional field rules from widget settings.
   *
   * @return array
   *   Array of parsed conditional rules, or empty array if none configured.
   */
  protected function getConditionalRules(): array {
    $rulesYaml = $this->getSetting('conditional_fields');

    if (is_array($rulesYaml)) {
      return $rulesYaml;
    }

    if (is_string($rulesYaml) && !empty($rulesYaml)) {
      return $this->parseRulesYaml($rulesYaml);
    }

    return [];
  }

  /**
   * Builds Drupal #states array from conditional rule values.
   *
   * @param string $triggerSelector
   *   CSS selector for the trigger field.
   * @param array $hideWhenValues
   *   Values that should hide the target field.
   * @param array $showWhenValues
   *   Values that should show the target field.
   *
   * @return array
   *   Drupal #states array.
   */
  protected static function buildStates(
    string $triggerSelector,
    array $hideWhenValues,
    array $showWhenValues,
  ): array {
    $states = [];

    if (!empty($hideWhenValues)) {
      $states['invisible'] = [
        $triggerSelector => array_map(
          fn($value) => ['value' => $value],
          $hideWhenValues
        ),
      ];
    }

    if (!empty($showWhenValues)) {
      $states['visible'] = [
        $triggerSelector => array_map(
          fn($value) => ['value' => $value],
          $showWhenValues
        ),
      ];
    }

    return $states;
  }

  /**
   * Builds a CSS selector for a form field to use with Drupal #states API.
   *
   * Attempts multiple strategies to determine the correct CSS selector for
   * a field, falling back to a flexible pattern match if exact selectors
   * cannot be determined.
   *
   * @param array $inlineForm
   *   The inline form array containing field definitions.
   * @param string $fieldName
   *   The machine name of the field.
   * @param bool $hasWidget
   *   Whether the field uses a widget wrapper structure.
   *
   * @return string
   *   A CSS selector string compatible with Drupal #states.
   */
  protected static function buildFieldSelector(array $inlineForm, string $fieldName, bool $hasWidget): string {
    $element = $inlineForm[$fieldName];

    if ($hasWidget && isset($element['widget']['#name'])) {
      return ':input[name="' . $element['widget']['#name'] . '"]';
    }

    if (isset($element['#name'])) {
      return ':input[name="' . $element['#name'] . '"]';
    }

    if ($hasWidget && isset($element['widget'][0]['value']['#name'])) {
      return ':input[name="' . $element['widget'][0]['value']['#name'] . '"]';
    }

    if (isset($element[0]['value']['#name'])) {
      return ':input[name="' . $element[0]['value']['#name'] . '"]';
    }

    if (isset($element['#attributes']['data-drupal-selector'])) {
      return '[data-drupal-selector="' . $element['#attributes']['data-drupal-selector'] . '"]';
    }

    return ':input[name*="[' . $fieldName . ']"]';
  }

  /**
   * {@inheritdoc}
   *
   * Clears target field values when they should be hidden based on conditional
   * rules with clear_when_hidden enabled.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $values = parent::massageFormValues($values, $form, $form_state);

    $rules = $this->getConditionalRules();
    if (empty($rules)) {
      return $values;
    }

    foreach ($values as &$value) {
      if (!isset($value['entity']) || !is_object($value['entity'])) {
        continue;
      }

      $entity = $value['entity'];

      foreach ($rules as $rule) {
        $triggerField = $rule['trigger_field'] ?? NULL;
        $targetField = $rule['target_field'] ?? NULL;
        $hideWhenValues = $rule['hide_when_values'] ?? [];
        $clearWhenHidden = $rule['clear_when_hidden'] ?? TRUE;

        if (!$triggerField || !$targetField || !$clearWhenHidden) {
          continue;
        }

        if (!$entity->hasField($triggerField) || !$entity->hasField($targetField)) {
          continue;
        }

        $triggerValue = $entity->get($triggerField)->value;
        if (in_array($triggerValue, $hideWhenValues, TRUE)) {
          $entity->set($targetField, NULL);
        }
      }
    }
    unset($value);

    return $values;
  }

  /**
   * Parses YAML-formatted conditional rules into an array.
   *
   * @param string $yaml
   *   YAML string containing conditional field rules.
   *
   * @return array
   *   Parsed rules array, or empty array on parse failure.
   */
  protected function parseRulesYaml(string $yaml): array {
    if (empty($yaml)) {
      return [];
    }

    try {
      $parsed = $this->serialization->decode($yaml);
      return is_array($parsed) ? $parsed : [];
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Failed to parse conditional field rules YAML: @message',
        ['@message' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Formats rules array as YAML for display in configuration textarea.
   *
   * @param array $rules
   *   Conditional rules array.
   *
   * @return string
   *   YAML-formatted string, or empty string on encoding failure.
   */
  protected function formatRulesForDisplay(array $rules): string {
    if (empty($rules)) {
      return '';
    }

    try {
      return $this->serialization->encode($rules);
    }
    catch (\Exception $e) {
      return '';
    }
  }

}
