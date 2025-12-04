<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for "Provided by" field with progressive disclosure.
 *
 * This widget provides a custom interface for selecting or creating public
 * organisations with the following workflow:
 *
 *  - Initially displays only the Type field
 *  - When type is selected:
 *    - For Local/Regional/National: Shows Country (mandatory)
 *      and organisation listing
 *    - For EU institutions: Shows organisation listing only
 *  - User can select existing or add new organisation
 *  - When "add new" is chosen: Hides listing, shows only
 *    Organisation Name field
 *
 * Implementation notes:
 *  - Uses a custom #op property on form elements to identify operations.
 *    This simplifies operation detection compared to parsing #name values,
 *    improving code maintainability and reducing complexity.
 */
#[FieldWidget(
  id: 'provided_by_widget',
  label: new TranslatableMarkup('Provided by widget'),
  field_types: ['entity_reference'],
)]
class ProvidedByWidget extends WidgetBase {

  use MessengerTrait;

  /**
   * Operation identifier for organisation type select.
   */
  protected const string OP_TYPE_SELECT = 'assessment_org_type_select';

  /**
   * Operation identifier for country select.
   */
  protected const string OP_COUNTRY_SELECT = 'assessment_org_country_select';

  /**
   * Operation identifier for add new organisation button.
   */
  protected const string OP_ADD_NEW = 'assessment_org_add_new';

  /**
   * Operation identifier for select existing organisation button.
   */
  protected const string OP_SELECT_EXISTING = 'assessment_org_select_existing';

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
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
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
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $renderable = parent::form($items, $form, $form_state, $get_delta);
    $renderable['#theme_wrappers']['details'] = [
      '#title' => $this->fieldDefinition->getLabel(),
      '#summary_attributes' => [],
      '#attributes' => ['open' => TRUE],
    ];
    return $renderable;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $fieldName = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];
    $widgetState = static::getWidgetState($parents, $fieldName, $form_state);
    $triggeringElement = $form_state->getTriggeringElement();

    $element['#type'] = 'container';
    $element['#attributes']['id'] = 'provided-by-content-' . $delta;
    $element['#attributes']['class'][] = 'provided-by-widget';
    $element['#attributes']['data-delta'] = $delta;

    $currentEntity = $items[$delta]->entity ?? NULL;
    $currentType = NULL;
    $currentCountry = NULL;

    // Reset current entity during country AJAX to prevent stale selection.
    // The 'country_just_changed' flag persists across multiple formElement()
    // calls during the AJAX rebuild process.
    if (!empty($widgetState['country_just_changed'])) {
      $currentEntity = NULL;
    }
    elseif ($currentEntity) {
      $currentType = $currentEntity->hasField('organisation_type')
        ? $currentEntity->get('organisation_type')->target_id
        : NULL;
      $currentCountry = $currentEntity->hasField('country')
        ? $currentEntity->get('country')->target_id
        : NULL;

      $stateType = $widgetState['selected_type'] ?? NULL;
      $stateCountry = $widgetState['selected_country'] ?? NULL;

      // Clear entity if type or country no longer match current selections.
      if (($stateType && $currentType != $stateType) ||
          ($stateCountry && $currentCountry != $stateCountry)) {
        $currentEntity = NULL;
        $currentType = NULL;
        $currentCountry = NULL;
      }
    }
    $element['organisation_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type of public sector body'),
      '#options' => $this->getOrganisationTypeOptions(),
      '#empty_option' => $this->t('- Select type -'),
      '#required' => $this->fieldDefinition->isRequired(),
      '#default_value' => $currentType,
      '#attributes' => [
        'class' => ['organisation-type-select'],
        'data-delta' => $delta,
      ],
      '#ajax' => [
        'callback' => [static::class, 'ajaxRefresh'],
        'wrapper' => 'provided-by-content-' . $delta,
        'effect' => 'fade',
      ],
      '#op' => self::OP_TYPE_SELECT,
      '#description' => (string) time(),
    ];

    $selectedType = NULL;
    $operation = $triggeringElement['#op'] ?? NULL;
    $isCountryAjax = $operation === self::OP_COUNTRY_SELECT;

    if ($operation === self::OP_TYPE_SELECT) {
      $selectedType = $form_state->getValue(array_merge($parents, [$fieldName, $delta, 'organisation_type']));

      $typeChanged = !isset($widgetState['selected_type']) ||
                      $widgetState['selected_type'] !== $selectedType;

      if ($typeChanged) {
        $widgetState['selected_country'] = NULL;
        $widgetState['add_new'] = FALSE;
        $currentEntity = NULL;
        $currentCountry = NULL;

        $this->clearTargetIdFromUserInput(
          $form_state,
          $parents,
          $fieldName,
          $delta
        );
      }

      $widgetState['selected_type'] = $selectedType;
      static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
    }
    elseif (isset($widgetState['selected_type'])) {
      $selectedType = $widgetState['selected_type'];
    }
    elseif ($currentType) {
      $selectedType = $currentType;
    }

    $element['content'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'provided-by-content',
          'assessments-inline',
        ],
      ],
      '#attached' => ['library' => ['assessments/inline-items']],
    ];

    if ($selectedType) {
      $requiresCountry = !$this->isEuOrganisationType($selectedType);

      $selectedCountry = NULL;
      if ($requiresCountry) {
        $selectedCountry = $this->getSelectedCountry(
          $form_state,
          $widgetState,
          $parents,
          $fieldName,
          $delta,
          $operation,
          $triggeringElement,
          $currentCountry
        );

        if ($selectedCountry) {
          // Avoid updating widget state during country AJAX to prevent
          // inconsistency across multiple formElement() invocations.
          if (!$isCountryAjax) {
            $countryChanged = !isset($widgetState['selected_country'])
              || $widgetState['selected_country'] !== $selectedCountry;

            if ($countryChanged) {
              $widgetState['add_new'] = FALSE;

              if ($currentEntity && isset($widgetState['selected_country'])) {
                $currentEntity = NULL;
              }

              if (isset($widgetState['selected_country'])) {
                $this->clearTargetIdFromUserInput($form_state, $parents, $fieldName, $delta);
              }
            }

            $widgetState['selected_country'] = $selectedCountry;
            static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
          }
        }
      }
      else {
        if (isset($widgetState['selected_country'])) {
          $widgetState['selected_country'] = NULL;
          static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
        }
      }
      if ($requiresCountry) {
        $countryOptions = $this->getCountryOptions();
        $defaultCountryValue = $selectedCountry ?? $currentCountry;

        $element['content']['country'] = [
          '#type' => 'select',
          '#title' => $this->t('Country'),
          '#options' => $countryOptions,
          '#empty_option' => $this->t('- Select country -'),
          '#required' => TRUE,
          '#default_value' => $defaultCountryValue,
          '#attributes' => [
            'class' => ['country-select'],
            'data-delta' => $delta,
          ],
          '#wrapper_attributes' => [
            'class' => [
              'assessments-inline__item',
            ],
          ],
          '#ajax' => [
            'callback' => [static::class, 'ajaxRefresh'],
            'wrapper' => 'provided-by-content-' . $delta,
            'effect' => 'fade',
          ],
          '#op' => self::OP_COUNTRY_SELECT,
        ];

        if (!$selectedCountry) {
          return $element;
        }
      }

      $addNew = $widgetState['add_new'] ?? FALSE;

      // Handle button clicks.
      if ($operation === self::OP_ADD_NEW) {
        $addNew = TRUE;
        $widgetState['add_new'] = TRUE;
        $widgetState['add_new_just_clicked'] = TRUE;
        unset($widgetState['country_just_changed']);
        static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
      }
      elseif ($operation === self::OP_SELECT_EXISTING) {
        $addNew = FALSE;
        $widgetState['add_new'] = FALSE;
        unset($widgetState['country_just_changed']);
        static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
        $this->clearAddNewFieldsFromUserInput($form_state, $parents, $fieldName, $delta);
      }

      $isSubmitting = $form_state->isSubmitted() && !$form_state->isRebuilding();
      $isEdit = !empty($items->getEntity()->id());

      if ($isSubmitting && isset($widgetState['country_just_changed'])) {
        unset($widgetState['country_just_changed']);
        static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
      }

      if ($isCountryAjax && !$isSubmitting) {
        $existingCount = $this->countMatchingOrganisations(
          $selectedType,
          $requiresCountry ? $selectedCountry : NULL
        );

        if ($existingCount > 0) {
          $addNew = FALSE;
          $widgetState['add_new'] = FALSE;
          $widgetState['country_just_changed'] = TRUE;
          static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
          $this->clearTargetIdFromUserInput($form_state, $parents, $fieldName, $delta);
        }
        else {
          $addNew = TRUE;
          $widgetState['add_new'] = TRUE;
          static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
        }
      }
      elseif ($isEdit && $currentEntity && $requiresCountry && $operation !== self::OP_ADD_NEW && $operation !== self::OP_SELECT_EXISTING) {
        $currentOrgCountry = $currentEntity->hasField('country')
          ? $currentEntity->get('country')->target_id
          : NULL;

        if ($selectedCountry == $currentOrgCountry && $addNew) {
          $addNew = FALSE;
          $widgetState['add_new'] = FALSE;
          static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
        }
      }
      elseif (!$addNew && !$isSubmitting) {
        $existingCount = $this->countMatchingOrganisations(
          $selectedType,
          $requiresCountry ? $selectedCountry : NULL
        );

        if ($existingCount === 0) {
          $addNew = TRUE;
          $widgetState['add_new'] = TRUE;
          static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
        }
      }

      if (!$addNew) {
        $organisations = $this->loadMatchingOrganisations($selectedType, $requiresCountry ? $selectedCountry : NULL);

        $options = [];
        foreach ($organisations as $org) {
          $options[$org->id()] = $org->label();
        }

        $defaultValue = NULL;
        if ($currentEntity && isset($options[$currentEntity->id()])) {
          $entityType = $currentEntity->hasField('organisation_type')
            ? $currentEntity->get('organisation_type')->target_id
            : NULL;
          $entityCountry = $currentEntity->hasField('country')
            ? $currentEntity->get('country')->target_id
            : NULL;

          $typeMatches = !$selectedType || $entityType == $selectedType;
          $countryMatches = !$requiresCountry || !$selectedCountry || $entityCountry == $selectedCountry;

          if ($typeMatches && $countryMatches) {
            $defaultValue = $currentEntity->id();
          }
        }

        $countryJustChanged = !empty($widgetState['country_just_changed']);

        $element['content']['target_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Select organisation'),
          '#options' => $options,
          '#empty_option' => $this->t('- Select -'),
          '#empty_value' => '_none',
          '#required' => $this->fieldDefinition->isRequired() && !$addNew,
          '#attributes' => [
            'class' => ['organisation-select'],
            'data-delta' => $delta,
          ],
          '#wrapper_attributes' => [
            'class' => [
              'assessments-inline__item',
            ],
          ],
        ] + ($countryJustChanged ? ['#value' => '_none'] : ['#default_value' => $defaultValue]);

        $element['content']['add_new_button'] = [
          '#type' => 'button',
          '#value' => $this->t('…or add new'),
          '#name' => $this->buildButtonName($parents, $fieldName, $delta, 'add_new_button'),
          '#attributes' => [
            'class' => [
              'button',
              'button--small',
              'add-new-organisation-button',
              'assessments-inline__item--compact',
            ],
          ],
          '#ajax' => [
            'callback' => [static::class, 'ajaxRefresh'],
            'wrapper' => 'provided-by-content-' . $delta,
            'effect' => 'fade',
          ],
          '#limit_validation_errors' => [],
          '#op' => self::OP_ADD_NEW,
        ];
      }
      else {
        $element['content']['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('New organisation name'),
          '#required' => TRUE,
          '#maxlength' => 255,
          '#default_value' => '',
          '#attributes' => [
            'class' => ['organisation-name-field'],
          ],
          '#wrapper_attributes' => [
            'class' => [
              'assessments-inline__item',
            ],
          ],
        ];

        // Hidden field to store the type and country for new organisation.
        $element['content']['new_org_type'] = [
          '#type' => 'value',
          '#value' => $selectedType,
        ];

        if ($requiresCountry) {
          $element['content']['new_org_country'] = [
            '#type' => 'value',
            '#value' => $selectedCountry,
          ];
        }

        $element['content']['create_new'] = [
          '#type' => 'value',
          '#value' => TRUE,
        ];

        $existingCount = $this->countMatchingOrganisations(
          $selectedType,
          $requiresCountry ? $selectedCountry : NULL
        );
        if ($existingCount > 0) {
          $element['content']['select_existing_button'] = [
            '#type' => 'button',
            '#value' => $this->t('…or select existing'),
            '#name' => $this->buildButtonName($parents, $fieldName, $delta, 'select_existing_button'),
            '#attributes' => [
              'class' => [
                'button',
                'button--small',
                'select-existing-button',
                'assessments-inline__item--compact',
              ],
            ],
            '#ajax' => [
              'callback' => [static::class, 'ajaxRefresh'],
              'wrapper' => 'provided-by-content-' . $delta,
              'effect' => 'fade',
            ],
            '#limit_validation_errors' => [],
            '#op' => self::OP_SELECT_EXISTING,
          ];
        }
      }
    }

    return $element;
  }

  /**
   * Get the selected country from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $widgetState
   *   The widget state.
   * @param array $parents
   *   The form parents.
   * @param string $fieldName
   *   The field name.
   * @param int $delta
   *   The field delta.
   * @param string|null $operation
   *   The current operation identifier.
   * @param mixed $triggeringElement
   *   The triggering element.
   * @param mixed $currentCountry
   *   The current country from entity.
   *
   * @return mixed
   *   The selected country ID or NULL.
   */
  private function getSelectedCountry(
    FormStateInterface $form_state,
    array $widgetState,
    array $parents,
    string $fieldName,
    int $delta,
    ?string $operation,
    mixed $triggeringElement,
    mixed $currentCountry,
  ): mixed {
    if ($operation === self::OP_COUNTRY_SELECT) {
      return $triggeringElement['#value'] ?? '';
    }

    if (isset($widgetState['selected_country'])) {
      return $widgetState['selected_country'];
    }

    if ($currentCountry) {
      return $currentCountry;
    }
    $userInput = $form_state->getUserInput();
    $inputPath = array_merge($parents, [$fieldName, $delta, 'content', 'country']);

    $tempInput = $userInput;
    foreach ($inputPath as $key) {
      if (!isset($tempInput[$key])) {
        return NULL;
      }
      $tempInput = $tempInput[$key];
    }

    return !empty($tempInput) ? $tempInput : NULL;
  }

  /**
   * Build a button name for AJAX elements.
   *
   * @param array $parents
   *   The form parents.
   * @param string $fieldName
   *   The field name.
   * @param int $delta
   *   The field delta.
   * @param string $buttonKey
   *   The button key (e.g., 'add_new_button', 'select_existing_button').
   *
   * @return string
   *   The button name for use in form element #name.
   */
  private function buildButtonName(array $parents, string $fieldName, int $delta, string $buttonKey): string {
    $buttonNameParts = $parents;
    $buttonNameParts[] = $fieldName;
    $buttonNameParts[] = $delta;
    $buttonNameParts[] = $buttonKey;
    $buttonName = array_shift($buttonNameParts);
    foreach ($buttonNameParts as $part) {
      $buttonName .= '[' . $part . ']';
    }
    return $buttonName;
  }

  /**
   * Clear target_id from user input to reset organization selection.
   *
   * This is necessary to reset the organization selection when
   * country or type changes.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $parents
   *   The form parents.
   * @param string $fieldName
   *   The field name.
   * @param int $delta
   *   The field delta.
   */
  private function clearTargetIdFromUserInput(FormStateInterface $form_state, array $parents, string $fieldName, int $delta): void {
    $paths = [
      array_merge($parents, [$fieldName, $delta, 'target_id']),
      array_merge($parents, [$fieldName, $delta, 'content', 'target_id']),
    ];

    foreach ($paths as $path) {
      $this->clearFieldFromFormState($form_state, $path);
    }
  }

  /**
   * Clear all "add new" fields from user input when switching to select mode.
   *
   * This ensures that the 'Organisation name', 'create_new', 'new_org_type',
   * and 'new_org_country' field values are removed when switching from
   * "add new" to "select existing" mode. This prevents unwanted organisation
   * creation when the form is submitted.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $parents
   *   The form parents.
   * @param string $fieldName
   *   The field name.
   * @param int $delta
   *   The field delta.
   */
  private function clearAddNewFieldsFromUserInput(FormStateInterface $form_state, array $parents, string $fieldName, int $delta): void {
    // List of fields to clear from "add new" mode.
    $fieldsToRemove = ['name', 'create_new', 'new_org_type', 'new_org_country'];

    foreach ($fieldsToRemove as $field) {
      $path = array_merge($parents, [$fieldName, $delta, 'content', $field]);
      $this->clearFieldFromFormState($form_state, $path);
    }
  }

  /**
   * Clear a field from both user input and form state values.
   *
   * This helper method removes a field value from both the user input array
   * and form state values to ensure complete cleanup.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $path
   *   The array path to the field (e.g., ['field_name', 0, 'content', 'name']).
   */
  private function clearFieldFromFormState(FormStateInterface $form_state, array $path): void {
    $userInput = $form_state->getUserInput();
    $temp = &$userInput;
    $pathCopy = $path;
    $lastKey = array_pop($pathCopy);

    foreach ($pathCopy as $key) {
      if (!isset($temp[$key])) {
        return;
      }
      $temp = &$temp[$key];
    }

    if (isset($temp[$lastKey])) {
      unset($temp[$lastKey]);
      $form_state->setUserInput($userInput);
    }

    $form_state->unsetValue($path);
  }

  /**
   * Get organisation type options from public_organisation entity field.
   *
   * @return array
   *   Array of organisation type options keyed by term ID.
   */
  protected function getOrganisationTypeOptions(): array {
    $options = [];

    try {
      $fieldDefinitions = $this->entityTypeManager
        ->getStorage('field_config')
        ->loadByProperties([
          'entity_type' => 'assessment',
          'bundle' => 'public_organisation',
          'field_name' => 'organisation_type',
        ]);

      if ($field_definition = reset($fieldDefinitions)) {
        $handlerSettings = $field_definition->getSetting('handler_settings');
        $options = $this->getTermOptionsFromHandlerSettings($handlerSettings);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load organisation type options: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $options;
  }

  /**
   * Get country options from public_organisation entity field.
   *
   * Uses the same handler (Views or default) as configured on the field.
   *
   * @return array
   *   Array of country options keyed by term ID.
   */
  protected function getCountryOptions(): array {
    $options = [];

    try {
      // Load the field definition from public_organisation entity type.
      $fieldDefinitions = $this->entityTypeManager
        ->getStorage('field_config')
        ->loadByProperties([
          'entity_type' => 'assessment',
          'bundle' => 'public_organisation',
          'field_name' => 'country',
        ]);

      if ($field_definition = reset($fieldDefinitions)) {
        $handler = $field_definition->getSetting('handler');
        $handlerSettings = $field_definition->getSetting('handler_settings');

        if ($handler === 'views') {
          $options = $this->getOptionsFromViewsHandler($handlerSettings);
        }
        else {
          $options = $this->getTermOptionsFromHandlerSettings($handlerSettings);
        }
      }
    }
    catch (\Exception $e) {
      // Fallback: Return empty options if field not available.
      $this->logger->error('Failed to load country options: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $options;
  }

  /**
   * Get term options from handler settings.
   *
   * Loads taxonomy terms from the configured vocabulary and applies sorting.
   *
   * @param array $handlerSettings
   *   The handler settings containing target bundles and sort configuration.
   *
   * @return array
   *   Array of term options keyed by term ID.
   */
  protected function getTermOptionsFromHandlerSettings(array $handlerSettings): array {
    $options = [];
    $targetBundles = $handlerSettings['target_bundles'] ?? [];

    if (!empty($targetBundles)) {
      $vocabulary = reset($targetBundles);
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties(['vid' => $vocabulary]);

      $sortField = $handlerSettings['sort']['field'] ?? 'name';
      $sortDirection = $handlerSettings['sort']['direction'] ?? 'asc';

      if ($sortField === 'name') {
        uasort($terms, function ($a, $b) use ($sortDirection) {
          $result = strcasecmp($a->label(), $b->label());
          return $sortDirection === 'asc' ? $result : -$result;
        });
      }

      foreach ($terms as $term) {
        $options[$term->id()] = $term->label();
      }
    }

    return $options;
  }

  /**
   * Get options from Views entity reference handler.
   *
   * @param array $handlerSettings
   *   The handler settings containing view configuration.
   *
   * @return array
   *   Array of options keyed by entity ID.
   */
  protected function getOptionsFromViewsHandler(array $handlerSettings): array {
    $options = [];

    try {
      $viewConfig = $handlerSettings['view'] ?? [];
      $viewName = $viewConfig['view_name'] ?? NULL;
      $displayName = $viewConfig['display_name'] ?? NULL;
      $arguments = $viewConfig['arguments'] ?? [];

      if ($viewName && $displayName) {
        $view = Views::getView($viewName);
        if ($view) {
          $view->setDisplay($displayName);
          $view->setArguments($arguments);
          $view->execute();

          foreach ($view->result as $row) {
            if (isset($row->_entity)) {
              $entity = $row->_entity;
              $options[$entity->id()] = $entity->label();
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load options from Views handler: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $options;
  }

  /**
   * Check if organisation type is EU-level.
   *
   * @param mixed $type
   *   The organisation type (term ID or machine name).
   *
   * @return bool
   *   TRUE if EU-level organisation.
   */
  protected function isEuOrganisationType(mixed $type): bool {
    if (empty($type)) {
      return FALSE;
    }

    // Load the term to check its machine name.
    try {
      $term = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->load($type);

      if ($term && $term->hasField('id')) {
        $machineName = $term->get('id')->value;
        return !empty($machineName) && in_array($machineName, [
          'european_union_institution',
          'european_union_agency',
          'european_union_body',
        ], TRUE);
      }
    }
    catch (\Exception) {
      return FALSE;
    }

    return FALSE;
  }

  /**
   * AJAX callback to refresh the content area.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element to return.
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    $triggeringElement = $form_state->getTriggeringElement();

    if (!$triggeringElement || empty($triggeringElement['#array_parents'])) {
      // Return empty response if no triggering element.
      return ['#type' => 'container'];
    }

    // Navigate to the current field item delta wrapper.
    $elementParents = $triggeringElement['#array_parents'];
    do {
      array_pop($elementParents);
      $currentLast = end($elementParents);
    } while (count($elementParents) > 1 && !is_numeric($currentLast));

    // Navigate to the element.
    $element = $form;
    foreach ($elementParents as $parent) {
      if (!isset($element[$parent])) {
        return ['#type' => 'container'];
      }
      $element = $element[$parent];
    }

    // Ensure the wrapper ID is set for AJAX replacement.
    $widgetIndex = array_search('widget', $triggeringElement['#array_parents']);
    $delta = $triggeringElement['#array_parents'][$widgetIndex + 1] ?? 0;
    $element['#attributes']['id'] = $element['#attributes']['id'] ?? 'provided-by-content-' . $delta;

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(
    array $values,
    array $form,
    FormStateInterface $form_state,
  ): array {
    $newValues = [];

    // Only create organisations during actual form submission, not during AJAX
    // rebuilds. This prevents premature creation when users switch modes or
    // change selections via AJAX.
    $isActualSubmission = $form_state->isSubmitted() && !$form_state->isRebuilding();

    // Get widget state to check if "Add New" was just clicked.
    $fieldName = $this->fieldDefinition->getName();
    $parents = $form['#parents'] ?? [];
    $widgetState = static::getWidgetState($parents, $fieldName, $form_state);
    $addNewJustClicked = $widgetState['add_new_just_clicked'] ?? FALSE;

    foreach ($values as $delta => $value) {
      if (empty($value)) {
        continue;
      }

      if (isset($value['target_id']) && !empty($value['target_id'])) {
        $newValues[$delta] = [
          'target_id' => $value['target_id'],
        ];
        continue;
      }

      if (isset($value['content']['target_id'])
        && !empty($value['content']['target_id'])
        && $value['content']['target_id'] !== '_none') {
        $newValues[$delta] = [
          'target_id' => $value['content']['target_id'],
        ];
        continue;
      }

      if ($isActualSubmission
        && isset($value['content']['create_new'])
        && $value['content']['create_new'] === TRUE
        && !empty($value['content']['name'])) {

        $organisationName = trim($value['content']['name']);
        $isEuType = !empty($value['content']['new_org_type']) &&
          $this->isEuOrganisationType($value['content']['new_org_type']);

        // Build query to find potential matches.
        $query = $this->entityTypeManager
          ->getStorage('assessment')
          ->getQuery()
          ->condition('type', 'public_organisation')
          ->condition('name', $organisationName)
          ->accessCheck()
          ->range(0, 1);

        if (!empty($value['content']['new_org_type'])) {
          $query->condition('organisation_type', $value['content']['new_org_type']);
        }

        if (!empty($value['content']['new_org_country'])) {
          $query->condition('country', $value['content']['new_org_country']);
        }

        $existingIds = $query->execute();

        // Reuse existing organisation if found.
        if (!empty($existingIds)) {
          $existingId = reset($existingIds);
          $newValues[$delta] = [
            'target_id' => $existingId,
          ];

          // Display warning message only when user just clicked "Add New".
          // This prevents showing the warning on simple edits like changing
          // the report title.
          if ($addNewJustClicked) {
            if ($isEuType) {
              $this->messenger()->addWarning($this->t(
                "You tried to create a new organisation but one with the same type and name already exists. We've linked the existing one to your report."
              ));
            }
            else {
              $this->messenger()->addWarning($this->t(
                "You tried to create a new organisation but one with the same type, country and name already exists. We've linked the existing one to your report."
              ));
            }
          }
        }

        if (!isset($newValues[$delta])) {
          $organisationValues = [
            'type' => 'public_organisation',
            'name' => $organisationName,
            'status' => TRUE,
          ];

          if (!empty($value['content']['new_org_type'])) {
            $organisationValues['organisation_type'] = $value['content']['new_org_type'];
          }

          if (!empty($value['content']['new_org_country'])) {
            $organisationValues['country'] = $value['content']['new_org_country'];
          }

          try {
            $organisation = $this->entityTypeManager
              ->getStorage('assessment')
              ->create($organisationValues);
            $organisation->save();

            $newValues[$delta] = [
              'target_id' => $organisation->id(),
            ];

            $this->logger->info('Created new organisation: @name (ID: @id)', [
              '@name' => $organisationName,
              '@id' => $organisation->id(),
            ]);
          }
          catch (\Exception $e) {
            $this->logger->error('Failed to create organisation "@name": @message', [
              '@name' => $organisationName,
              '@message' => $e->getMessage(),
            ]);
          }
        }
        continue;
      }
    }

    // Clear the "add_new_just_clicked" flag after processing.
    if ($addNewJustClicked && $isActualSubmission) {
      unset($widgetState['add_new_just_clicked']);
      static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
    }

    return $newValues;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    $originalSettings = $this->getSettings();
    parent::extractFormValues($items, $form, $form_state);
    $this->settings = $originalSettings;
  }

  /**
   * Build a base query for organisations with filters.
   *
   * @param mixed $type
   *   The organisation type term ID.
   * @param mixed $country
   *   The country term ID (optional).
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object with filters applied.
   */
  private function buildOrganisationQuery(mixed $type, mixed $country = NULL): QueryInterface {
    $query = $this->entityTypeManager
      ->getStorage('assessment')
      ->getQuery()
      ->condition('type', 'public_organisation')
      ->accessCheck();

    // Filter by organisation type if provided.
    if (!empty($type)) {
      $typeId = is_numeric($type) ? (int) $type : NULL;
      if ($typeId) {
        $query->condition('organisation_type', $typeId);
      }
    }

    // Filter by country if provided.
    if (!empty($country)) {
      $countryId = is_numeric($country) ? (int) $country : NULL;
      if ($countryId) {
        $query->condition('country', $countryId);
      }
    }

    return $query;
  }

  /**
   * Count organisations matching the given filters.
   *
   * @param mixed $type
   *   The organisation type term ID.
   * @param mixed $country
   *   The country term ID (optional, can be int or string).
   *
   * @return int
   *   The count of matching organisations.
   */
  protected function countMatchingOrganisations(mixed $type, mixed $country = NULL): int {
    try {
      $query = $this->buildOrganisationQuery($type, $country);
      $count = (int) $query->count()->execute();

      return $count;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to count organisations: @message', ['@message' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Load matching organisations based on type and country.
   *
   * @param mixed $type
   *   The organisation type ID.
   * @param mixed $country
   *   The country ID (optional).
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of matching organisation entities.
   */
  private function loadMatchingOrganisations(mixed $type, mixed $country = NULL): array {
    try {
      $query = $this->buildOrganisationQuery($type, $country);
      $query->sort('name');
      $entityIds = $query->execute();
      return $this->entityTypeManager->getStorage('assessment')->loadMultiple($entityIds);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load organisations: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
