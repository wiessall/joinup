<?php

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @FieldFormatter(
 *   id = "joinup_core_formatter_fallback_field",
 *   label = @Translation("Formatter with fallback field"),
 *   deriver = "Drupal\joinup_core\Plugin\Deriver\FormatterWithFallbackFieldDeriver"
 * )
 */
class FormatterWithFallbackField extends FormatterBase implements ContainerFactoryPluginInterface {

  const MAIN_FIELD_FORMATTER_ID_KEY = 'main_field_formatter_id';
  const MAIN_FIELD_FORMATTER_SETTINGS_KEY = 'main_field_formatter_settings';
  const FALLBACK_FIELD_ID_KEY = 'fallback_field_id';
  const FALLBACK_FIELD_FORMATTER_ID_KEY = 'fallback_field_formatter_id';
  const FALLBACK_FIELD_FORMATTER_SETTINGS_KEY = 'fallback_field_formatter_settings';

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      static::MAIN_FIELD_FORMATTER_ID_KEY => NULL,
      static::MAIN_FIELD_FORMATTER_SETTINGS_KEY => [],
      static::FALLBACK_FIELD_ID_KEY => NULL,
      static::FALLBACK_FIELD_FORMATTER_ID_KEY => NULL,
      static::FALLBACK_FIELD_FORMATTER_SETTINGS_KEY => [],
    ] + parent::defaultSettings();
  }

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $pluginId
   *   The plugin_id for the formatter.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $viewMode
   *   The view mode.
   * @param array $thirdPartySettings
   *   Any third party settings.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $formatterPluginManager
   *   The field formatter plugin manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypeManager
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   */
  public function __construct(
    string $pluginId,
    $pluginDefinition,
    FieldDefinitionInterface $fieldDefinition,
    array $settings,
    $label,
    string $viewMode,
    array $thirdPartySettings,
    protected PluginManagerInterface $formatterPluginManager,
    protected FieldTypePluginManagerInterface $fieldTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager
  ) {
    parent::__construct(
      $pluginId,
      $pluginDefinition,
      $fieldDefinition,
      $settings,
      $label,
      $viewMode,
      $thirdPartySettings
    );
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.field.formatter'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $renderable = [];

    if (!($mainFieldFormatterId = $this->getSetting(static::MAIN_FIELD_FORMATTER_ID_KEY))) {
      return $renderable;
    }

    if (!$items->isEmpty()) {
      $mainFormatter = $this->formatterPluginManager->getInstance([
        'field_definition' => $this->fieldDefinition,
        'configuration' => [
          'type' => $mainFieldFormatterId,
          'settings' => $this->getSetting(static::MAIN_FIELD_FORMATTER_SETTINGS_KEY),
          'label' => $this->label,
          'weight' => 0,
        ],
        'view_mode' => $this->viewMode,
      ]);
      $renderable = $mainFormatter->viewElements($items, $langcode);
    }

    // This part doesn't work with Layout Builder, because when its FieldBlock
    // plugin checks block access, it also involves checking field item list
    // emptiness - and in case of empty fields the access check returns
    // AccessResultForbidden. We have (and need) LayoutBuilderBuildSubscriber to
    // work around that case. But with Field UI, the situation is much easier,
    // we can render the fallback field here.
    if (
      empty($renderable) &&
      ($fallbackFieldId = $this->getSetting(static::FALLBACK_FIELD_ID_KEY)) &&
      ($fallbackFieldFormatterId = $this->getSetting(static::FALLBACK_FIELD_FORMATTER_ID_KEY))
    ) {
      $fallbackFormatter = $this->formatterPluginManager->getInstance([
        'field_definition' => $this->fieldDefinition,
        'configuration' => [
          'type' => $fallbackFieldFormatterId,
          'settings' => $this->getSetting(static::FALLBACK_FIELD_FORMATTER_SETTINGS_KEY),
          'label' => $this->label,
          'weight' => 0,
        ],
        'view_mode' => $this->viewMode,
      ]);

      $fallbackFieldItemList = $items->getEntity()->hasField($fallbackFieldId)
        ? $items->getEntity()->get($fallbackFieldId)
        : NULL;

      if ($fallbackFieldItemList && !$fallbackFieldItemList->isEmpty()) {
        $renderable = $fallbackFormatter->viewElements($fallbackFieldItemList, $langcode);
      }
    }

    return $renderable;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    if (
      !($mainFormatterId = $this->getSetting(static::MAIN_FIELD_FORMATTER_ID_KEY)) ||
      !($fallbackField = $this->getSetting(static::FALLBACK_FIELD_ID_KEY)) ||
      !($fallbackFormatterId = $this->getSetting(static::FALLBACK_FIELD_FORMATTER_ID_KEY))
    ) {
      return [$this->t('Not yet configured')];
    }

    $mainFormatter = $this->formatterPluginManager->getInstance([
      'field_definition' => $this->fieldDefinition,
      'configuration' => [
        'type' => $mainFormatterId,
        'settings' => $this->getSetting(static::MAIN_FIELD_FORMATTER_SETTINGS_KEY),
        'label' => '',
      ],
      'view_mode' => '_custom',
    ]);

    $summary[] = $this->t('Main field formatter: %formatter', [
      '%formatter' => $mainFormatter->getPluginDefinition()['label'],
    ]);
    $summary = array_merge(
      $summary,
      (array) $mainFormatter->settingsSummary()
    );

    $fallbackFieldDefinition = $this->getFallbackFieldDefinitions()[$fallbackField];
    $fallbackFormatter = $this->formatterPluginManager->getInstance([
      'field_definition' => $fallbackFieldDefinition,
      'configuration' => [
        'type' => $fallbackFormatterId,
        'settings' => $this->getSetting(static::FALLBACK_FIELD_FORMATTER_SETTINGS_KEY),
        'label' => '',
      ],
      'view_mode' => '_custom',
    ]);

    $summary[] = '---';
    $summary[] = $this->t('Fallback field: %field', [
      '%field' => $fallbackFieldDefinition->getLabel(),
    ]);
    $summary[] = $this->t('Fallback field formatter: %formatter', [
      '%formatter' => $fallbackFormatter->getPluginDefinition()['label'],
    ]);
    return array_merge(
      $summary,
      (array) $fallbackFormatter->settingsSummary()
    );
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

    // Formatter configurations for the current field (applied if field is not
    // empty).
    $form += $this->getFormatterElementsForField(
      $this->fieldDefinition,
      'main_field_formatter',
      $formState,
      $this->configuration,
      $this->t('Main field'),
    );

    // Visual separator between main and fallback field formatter configs.
    $form[] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
    ];

    // The fallback field and its formatter configuration. First, select the
    // fallback field.
    $avaliableFallbackFieldDefinitions = $this->getFallbackFieldDefinitions();
    $fallbackFieldId = $this->getValuesFromFormState($formState, static::FALLBACK_FIELD_ID_KEY);
    $fallbackFieldFormOptions = array_map(
      fn ($definition) => $definition->getLabel() ?: $definition->getName(),
      $avaliableFallbackFieldDefinitions
    );
    $form[static::FALLBACK_FIELD_ID_KEY] = [
      '#type' => 'select',
      '#title' => $this->t('Fallback field'),
      '#options' => $fallbackFieldFormOptions,
      '#default_value' => $fallbackFieldId ?: NULL,
      '#ajax' => [
        'callback' => [get_class($this), 'onFallbackFieldNameChange'],
      ],
      '#required' => TRUE,
    ];

    $form += $this->getFormatterElementsForField(
      $avaliableFallbackFieldDefinitions[$fallbackFieldId] ?? NULL,
      'fallback_field_formatter',
      $formState,
      $this->configuration,
      $this->t('Fallback field'),
    );

    return $form;
  }

  /**
   * Returns formatter plugin select and formatter settings elements of a field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface|null $fieldDefinition
   *   The field definition of the field. Method returns only placeholder
   *   containers with the appropriate HTML IDs if NULL.
   * @param string $formElementsKeyBase
   *   Base string to use as the base of the formatter ID select element and the
   *   parent key of formatter settings elements.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object of the Field UI / Layout Builder block configure
   *   form.
   * @param array $displayOptions
   *   The display options array.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $fieldLabelContext
   *   Label context to use in the label of the formatter ID select element.
   *
   * @return array[]
   *   Form elements with the formatter ID select element and the form elements
   *   of the selected formatter.
   */
  protected function getFormatterElementsForField(
    ?FieldDefinitionInterface $fieldDefinition,
    string $formElementsKeyBase,
    FormStateInterface $formState,
    array $displayOptions,
    string|TranslatableMarkup $fieldLabelContext
  ): array {
    $formatterIdKey = "{$formElementsKeyBase}_id";
    $formatterSettingsKey = "{$formElementsKeyBase}_settings";
    $formatterIdAjaxId = Html::getId($formatterIdKey) . '-ajax';
    $formatterSettingsAjaxId = Html::getId($formatterSettingsKey) . '-ajax';

    // No field definition, return only the placeholders required for AJAX form
    // update.
    if (!$fieldDefinition) {
      return [
        $formatterIdKey => [
          '#type' => 'container',
          '#optional' => FALSE,
          '#id' => $formatterIdAjaxId,
        ],
        $formatterSettingsKey => [
          '#type' => 'container',
          '#optional' => FALSE,
          '#id' => Html::getId($formatterSettingsKey) . '-ajax',
        ],
      ];
    }

    // Build the formatter plugin ID select element.
    $formatters = $this->getApplicablePluginOptions($fieldDefinition);
    $currentFormatterId = $this->getValuesFromFormState($formState, $formatterIdKey);
    if (
      !$currentFormatterId ||
      !array_key_exists($currentFormatterId, $formatters)
    ) {
      $currentFormatterId = $this->getDefaultFormatterId($fieldDefinition->getType());
    }
    $form[$formatterIdKey] = [
      '#type' => 'select',
      '#title' => $this->t('Plugin for @title', [
        '@title' => $fieldLabelContext,
      ]),
      '#options' => $formatters,
      '#default_value' => $currentFormatterId,
      '#ajax' => [
        'callback' => [get_class(), 'onFormatterTypeChange'],
        'wrapper' => $formatterSettingsAjaxId,
        'event' => 'change',
        'method' => 'replaceWith',
      ],
      '#prefix' => "<div id=\"$formatterIdAjaxId\">",
      '#suffix' => '</div>',
      '#required' => TRUE,
    ];

    // Elements of the formatter settings form.
    $formatter = $this->formatterPluginManager->getInstance([
      'field_definition' => $fieldDefinition,
      'configuration' => [
        'type' => $currentFormatterId,
        'settings' => $displayOptions[$formatterSettingsKey] ?? [],
        'label' => '',
        'weight' => 0,
      ],
      'view_mode' => '_custom',
    ]);
    $form[$formatterSettingsKey] = $formatter->settingsForm([], $formState)
      ?: ['#value' => []];
    $form[$formatterSettingsKey]['#prefix'] = "<div id=\"$formatterSettingsAjaxId\">";
    $form[$formatterSettingsKey]['#suffix'] = '</div>';

    return $form;
  }

  /**
   * Return formatter value from form state (falling back to current settings).
   *
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state object.
   * @param string|array $keys
   *   Keys representing levels in form structure, starting from this field
   *   formatter's "settings" element.
   *
   * @return mixed
   *   The value from form state object, or if it isn't present there, from the
   *   current settings property.
   */
  protected function getValuesFromFormState(
    FormStateInterface $formState,
    string|array $keys
  ): mixed {
    $path = [
      'fields',
      $this->fieldDefinition->getName(),
      'settings_edit_form',
      'settings',
      ... (array) $keys
    ];

    if ($formState->hasValue($path)) {
      return $formState->getValue($path);
    }

    // Fall back to saved settings.
    if (!$this->defaultSettingsMerged) {
      $this->mergeDefaults();
    }
    $valuesToReturn = $this->settings;

    foreach ((array) $keys as $key) {
      $valuesToReturn = $valuesToReturn[$key] ?? NULL;
      if ($valuesToReturn === NULL) {
        return NULL;
      }
    }
    return $valuesToReturn;
  }

  /**
   * Returns an array of applicable formatter options for a field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   *
   * @return array
   *   An array of applicable formatter plugin labels, keyed by their plugin ID.
   */
  protected function getApplicablePluginOptions(FieldDefinitionInterface $fieldDefinition) {
    $options = $this->formatterPluginManager->getOptions($fieldDefinition->getType());
    // Exclude this type.
    unset($options[$this->getPluginId()]);
    $applicable_options = [];
    foreach ($options as $option => $label) {
      $plugin_class = DefaultFactory::getPluginClass($option, $this->formatterPluginManager->getDefinition($option));
      if ($plugin_class::isApplicable($fieldDefinition)) {
        $applicable_options[$option] = $label;
      }
    }
    return $applicable_options;
  }

  /**
   * Returns the plugin ID of the given field type's default formatter.
   *
   * @param string $fieldType
   *   The type of the field.
   *
   * @return string|null
   *   Plugin ID of the field type's default formatter. Might be empty if the
   *   field type has no default formatter (e.g. because it does not have UI).
   */
  protected function getDefaultFormatterId(string $fieldType): ?string {
    return $this->fieldTypeManager->getDefinitions()[$fieldType]['default_formatter'] ?? NULL;
  }

  /**
   * Returns the list of the applicable fallback field definitions.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   List of the applicable fallback field definitions. The current field and
   *   fields not being display configurable are excluded.
   */
  protected function getFallbackFieldDefinitions(): array {
    $field_defs = $this->entityFieldManager->getFieldDefinitions(
      $this->fieldDefinition->getTargetEntityTypeId(),
      $this->fieldDefinition->getTargetBundle()
    );
    // Remove the current field from the list.
    unset($field_defs[$this->fieldDefinition->getName()]);

    // Remove fields which are marked as non-configurable.
    return array_filter(
      $field_defs,
      fn (FieldDefinitionInterface $definition) => $definition->isDisplayConfigurable('view')
    );
  }

  /**
   * Ajax callback updating formatter subform if fallback field is changed.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax commands updating the formatter form (Field UI / Layout Builder form
   *   substructure).
   */
  public static function onFallbackFieldNameChange(array $form, FormStateInterface $formState) {
    $triggeringElement = $formState->getTriggeringElement();

    $response = new AjaxResponse();

    if (!empty($triggeringElement['#array_parents'])) {
      $parents = $triggeringElement['#array_parents'];
      // Remove the triggering fallback field select element.
      array_pop($parents);

      // Replace the formatter plugin ID select element.
      $response->addCommand(
        new ReplaceCommand(
          '#fallback-field-formatter-id-ajax',
          NestedArray::getValue(
            $form,
            array_merge($parents, [static::FALLBACK_FIELD_FORMATTER_ID_KEY])
          )
        )
      );

      // Replace the formatter plugin settings elements.
      $response->addCommand(
        new ReplaceCommand(
          '#fallback-field-formatter-settings-ajax',
          NestedArray::getValue(
            $form,
            array_merge($parents, [static::FALLBACK_FIELD_FORMATTER_SETTINGS_KEY])
          )
        )
      );
    }

    return $response;
  }

  /**
   * Ajax callback updating formatter subform if a formatter field is changed.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return array
   *   Replacement renderable array of the formatter settings form elements.
   */
  public static function onFormatterTypeChange(array $form, FormStateInterface $formState): array {
    $triggeringElement = $formState->getTriggeringElement();

    if (!empty($triggeringElement['#array_parents'])) {
      $parents = $triggeringElement['#array_parents'];
      // Remove the triggering element and add the appropriate "*_settings" key
      // instead.
      $plugin_id_key = array_pop($parents);
      $parents[] = preg_replace('/^(.*)_id$/', '$1_settings', $plugin_id_key);

      return NestedArray::getValue($form, $parents);
    }

    return [];
  }

}
