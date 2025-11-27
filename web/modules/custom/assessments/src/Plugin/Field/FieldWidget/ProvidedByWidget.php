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
 */
#[FieldWidget(
  id: 'provided_by_widget',
  label: new TranslatableMarkup('Provided by widget'),
  field_types: ['entity_reference'],
)]
final class ProvidedByWidget extends WidgetBase {

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
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $fieldName = $this->fieldDefinition->getName();
    $parents = $element['#field_parents'];
    $widgetState = static::getWidgetState($parents, $fieldName, $form_state);

    $element['#type'] = 'fieldset';
    $element['#title'] = $this->fieldDefinition->getLabel();
    $element['#attributes']['class'][] = 'provided-by-widget';
    $element['#attributes']['data-delta'] = $delta;

    $currentEntity = $items[$delta]->entity ?? NULL;
    $currentType = NULL;
    $currentCountry = NULL;

    // If entity exists, check if its country/type match the current
    // form selections. This prevents showing stale entity after
    // country/type changes during AJAX.
    if ($currentEntity) {
      $currentType = $currentEntity->hasField('organisation_type')
        ? $currentEntity->get('organisation_type')->target_id
        : NULL;
      $currentCountry = $currentEntity->hasField('country')
        ? $currentEntity->get('country')->target_id
        : NULL;

      $stateType = $widgetState['selected_type'] ?? NULL;
      $stateCountry = $widgetState['selected_country'] ?? NULL;

      if ($stateType && $currentType != $stateType) {
        $currentEntity = NULL;
        $currentType = NULL;
        $currentCountry = NULL;
      }
      elseif ($stateCountry && $currentCountry != $stateCountry) {
        $currentEntity = NULL;
        $currentType = NULL;
        $currentCountry = NULL;
      }
    }

    // Step 1: Organisation Type field (always visible).
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
    ];

    $triggeringElement = $form_state->getTriggeringElement();
    $selectedType = NULL;

    if ($triggeringElement && isset($triggeringElement['#name']) &&
        strpos($triggeringElement['#name'], '[organisation_type]') !== FALSE) {
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
        'class' => ['provided-by-content'],
        'id' => 'provided-by-content-' . $delta,
      ],
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
          $triggeringElement,
          $currentCountry
        );

        if ($selectedCountry) {
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
      else {
        if (isset($widgetState['selected_country'])) {
          $widgetState['selected_country'] = NULL;
          static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
        }
      }

      // Step 2: Country field (if required by type).
      if ($requiresCountry) {
        $element['content']['country'] = [
          '#type' => 'select',
          '#title' => $this->t('Country'),
          '#options' => $this->getCountryOptions(),
          '#empty_option' => $this->t('- Select country -'),
          '#required' => TRUE,
          '#default_value' => $selectedCountry ?? $currentCountry,
          '#attributes' => [
            'class' => ['country-select'],
            'data-delta' => $delta,
          ],
          '#ajax' => [
            'callback' => [static::class, 'ajaxRefresh'],
            'wrapper' => 'provided-by-content-' . $delta,
            'effect' => 'fade',
          ],
        ];

        // Only show organisation selector if country is selected.
        if (!$selectedCountry) {
          return $element;
        }
      }

      // Check if user wants to add new organisation.
      $addNew = $widgetState['add_new'] ?? FALSE;

      if ($triggeringElement && isset($triggeringElement['#name']) &&
          strpos($triggeringElement['#name'], '[add_new_button]') !== FALSE) {
        $addNew = TRUE;
        $widgetState['add_new'] = TRUE;
        static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
      }
      elseif ($triggeringElement && isset($triggeringElement['#name']) &&
               strpos($triggeringElement['#name'], '[select_existing_button]') !== FALSE) {
        $addNew = FALSE;
        $widgetState['add_new'] = FALSE;
        static::setWidgetState($parents, $fieldName, $form_state, $widgetState);
      }

      // Check if there are any existing organisations matching the filters.
      // If not, automatically show "add new" form.
      if (!$addNew) {
        $countType = $selectedType;
        $countCountry = $requiresCountry ? $selectedCountry : NULL;
        $existingCount = $this->countMatchingOrganisations($countType, $countCountry);

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
          $defaultValue = $currentEntity->id();
        }

        $element['content']['target_id'] = [
          '#type' => 'select',
          '#title' => $this->t('Select organisation'),
          '#options' => $options,
          '#empty_option' => $this->t('- Select -'),
          '#default_value' => $defaultValue,
          '#required' => $this->fieldDefinition->isRequired() && !$addNew,
          '#attributes' => [
            'class' => ['organisation-select'],
            'data-delta' => $delta,
          ],
        ];

        $element['content']['add_new_button'] = [
          '#type' => 'button',
          '#value' => $this->t('Add new organisation'),
          '#name' => $this->buildButtonName($parents, $fieldName, $delta, 'add_new_button'),
          '#attributes' => [
            'class' => ['button', 'button--small', 'add-new-organisation-button'],
          ],
          '#ajax' => [
            'callback' => [static::class, 'ajaxRefresh'],
            'wrapper' => 'provided-by-content-' . $delta,
            'effect' => 'fade',
          ],
          '#limit_validation_errors' => [],
        ];
      }
      else {
        // Step 3b: Show "add new" form.
        $element['content']['name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Organisation name'),
          '#required' => TRUE,
          '#maxlength' => 255,
          '#default_value' => '',
          '#attributes' => [
            'class' => ['organisation-name-field'],
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

        // Only show "select existing" button if there are organisations
        // to select.
        $existingCount = $this->countMatchingOrganisations(
          $selectedType,
          $requiresCountry ? $selectedCountry : NULL
        );
        if ($existingCount > 0) {
          // Button to switch back to "select existing" mode.
          $element['content']['select_existing_button'] = [
            '#type' => 'button',
            '#value' => $this->t('Select existing organisation'),
            '#name' => $this->buildButtonName($parents, $fieldName, $delta, 'select_existing_button'),
            '#attributes' => [
              'class' => ['button', 'button--small', 'select-existing-button'],
            ],
            '#ajax' => [
              'callback' => [static::class, 'ajaxRefresh'],
              'wrapper' => 'provided-by-content-' . $delta,
              'effect' => 'fade',
            ],
            '#limit_validation_errors' => [],
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
    $triggeringElement,
    $currentCountry,
  ): mixed {
    // Check if country was just selected via AJAX.
    if ($triggeringElement && isset($triggeringElement['#name'])
      && strpos($triggeringElement['#name'], '[country]') !== FALSE) {
      return $form_state->getValue(array_merge(
        $parents,
        [$fieldName, $delta, 'content', 'country']
      ));
    }

    // Check widget state for previously selected country.
    if (isset($widgetState['selected_country'])) {
      return $widgetState['selected_country'];
    }

    // Check if we have a current country from existing entity.
    if ($currentCountry) {
      return $currentCountry;
    }

    // Fallback: check user input directly for AJAX rebuilds.
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
   * Clear the target_id value from user input.
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
    $userInput = $form_state->getUserInput();
    $targetPath = array_merge($parents, [$fieldName, $delta, 'target_id']);

    $temp = &$userInput;
    $lastKey = array_pop($targetPath);
    foreach ($targetPath as $key) {
      if (!isset($temp[$key])) {
        $temp[$key] = [];
      }
      $temp = &$temp[$key];
    }
    unset($temp[$lastKey]);
    $form_state->setUserInput($userInput);
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
      // Load the field definition from public_organisation entity type.
      $fieldDefinitions = $this->entityTypeManager
        ->getStorage('field_config')
        ->loadByProperties([
          'entity_type' => 'assessment',
          'bundle' => 'public_organisation',
          'field_name' => 'organisation_type',
        ]);

      if ($field_definition = reset($fieldDefinitions)) {
        $handlerSettings = $field_definition->getSetting('handler_settings');
        $targetBundles = $handlerSettings['target_bundles'] ?? [];

        // Load terms from the configured vocabulary.
        if (!empty($targetBundles)) {
          $vocabulary = reset($targetBundles);
          $terms = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->loadByProperties(['vid' => $vocabulary]);

          // Apply sorting if configured.
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
      }
    }
    catch (\Exception $e) {
      // Fallback: Return empty options if field not available.
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

        // If using Views handler, get options from the View.
        if ($handler === 'views') {
          $options = $this->getOptionsFromViewsHandler($handlerSettings);
        }
        // Otherwise use default taxonomy handler.
        else {
          $targetBundles = $handlerSettings['target_bundles'] ?? [];
          if (!empty($targetBundles)) {
            $vocabulary = reset($targetBundles);
            $terms = $this->entityTypeManager
              ->getStorage('taxonomy_term')
              ->loadByProperties(['vid' => $vocabulary]);

            // Apply sorting if configured.
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
    catch (\Exception $e) {
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

    // Navigate to the content wrapper.
    $elementParents = $triggeringElement['#array_parents'];

    // Remove the last element (the triggering element itself).
    array_pop($elementParents);

    // If the last element is not 'content', we need to add it.
    // This handles the case where organisation_type triggers AJAX.
    // When country or buttons trigger AJAX, 'content' is already in the path.
    if (end($elementParents) !== 'content') {
      $elementParents[] = 'content';
    }

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

    foreach ($values as $delta => $value) {

      // Check if there's a direct target_id (from existing entity
      // in edit mode).
      if (isset($value['target_id']) && !empty($value['target_id'])) {
        $newValues[$delta] = [
          'target_id' => $value['target_id'],
        ];
        continue;
      }

      // Handle "select existing" mode from dropdown.
      if (isset($value['content']['target_id'])
        && !empty($value['content']['target_id'])) {
        $newValues[$delta] = [
          'target_id' => $value['content']['target_id'],
        ];
        continue;
      }

      // Handle "create new" mode - ONLY if create_new flag is set
      // AND name is provided.
      if (isset($value['content']['create_new'])
        && $value['content']['create_new'] === TRUE
        && !empty($value['content']['name'])) {

        $organisationName = trim($value['content']['name']);

        // Check if an EXACT duplicate already exists
        // (same name + type + country).
        // We only prevent duplicates when ALL fields match.
        $query = $this->entityTypeManager
          ->getStorage('assessment')
          ->getQuery()
          ->condition('type', 'public_organisation')
          ->condition('name', $organisationName)
          ->accessCheck()
          ->range(0, 1);

        // MUST match on type if provided.
        $hasType = FALSE;
        if (!empty($value['content']['new_org_type'])) {
          // new_org_type is already a term ID (no conversion needed).
          $query->condition(
            'organisation_type',
            $value['content']['new_org_type']
          );
          $hasType = TRUE;
        }

        // MUST match on country if provided.
        $hasCountry = FALSE;
        if (!empty($value['content']['new_org_country'])) {
          $query->condition('country', $value['content']['new_org_country']);
          $hasCountry = TRUE;
        }

        $existingIds = $query->execute();

        // Only use existing if we have a complete match
        // (name + type + country).
        // This allows same name with different type or country to
        // create new entities.
        if (!empty($existingIds) && $hasType) {
          // For EU organisations, we only need name + type match
          // (no country). For national/regional/local, we need
          // name + type + country match.
          $isEuType = $this->isEuOrganisationType($value['content']['new_org_type']);

          if ($isEuType || $hasCountry) {
            // Complete match found - use existing organisation.
            $existingId = reset($existingIds);
            $newValues[$delta] = [
              'target_id' => $existingId,
            ];
          }
        }

        // If we didn't find and use an existing organisation, create a new one.
        if (!isset($newValues[$delta])) {
          // Create new organisation entity.
          $organisationValues = [
            'type' => 'public_organisation',
            'name' => $organisationName,
            'status' => TRUE,
          ];

          // Add organisation type (already a term ID).
          if (!empty($value['content']['new_org_type'])) {
            $organisationValues['organisation_type'] = $value['content']['new_org_type'];
          }

          // Add country if provided.
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
          }
          catch (\Exception $e) {
            $this->logger->error('Failed to create organisation: @message', ['@message' => $e->getMessage()]);
          }
        }
        continue;
      }
    }

    return $newValues;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    // Temporarily remove filter settings to prevent validation errors
    // with newly created entities.
    $originalSettings = $this->getSettings();

    // Call parent to extract values.
    parent::extractFormValues($items, $form, $form_state);

    // Restore original settings.
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
      return (int) $this->buildOrganisationQuery($type, $country)->count()->execute();
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
