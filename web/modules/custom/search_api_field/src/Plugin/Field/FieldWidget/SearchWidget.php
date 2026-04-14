<?php

declare(strict_types=1);

namespace Drupal\search_api_field\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api_field\Event\AlterQueryPresetsDescriptionEvent;
use Drupal\search_api_field\Plugin\Field\FieldType\SearchItem;
use Drupal\search_api_field\Plugin\FilterPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Plugin implementation of the 'search_api_field_default' widget.
 *
 * Provides a field widget for configuring Search API-based content listings.
 * Allows administrators to configure facets, filters, and display options
 * for a dynamic search results block.
 */
#[FieldWidget(
  id: 'search_api_field_default',
  label: new TranslatableMarkup('Search widget'),
  field_types: ['search_api_field'],
)]
class SearchWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FilterPluginManagerInterface $filterPluginManager,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
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
      $container->get('plugin.manager.search_api_field.filter'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'query_builder' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['query_builder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable query builder.'),
      '#description' => $this->t('Shows the query builder interface in the form.'),
      '#default_value' => !empty($this->getSetting('query_builder')),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if (!empty($this->getSetting('query_builder'))) {
      $summary[] = $this->t('Query builder enabled.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\search_api_field\Plugin\Field\FieldType\SearchItem $item */
    $item = $items[$delta];
    $default_values = $item->get('value')->getValue();
    $facets = $this->getFacets();

    $element['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the search field'),
      '#description' => $this->t('Related content from the community will be displayed below the one that you are publishing.'),
      '#default_value' => $default_values['enabled'] ?? TRUE,
    ];

    // Wrap all the remaining elements so they can be hidden when the above
    // checkbox is unchecked.
    $element['wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="' . $this->getNameOfEnabledField($delta, $element) . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element += [
      '#extra' => array_keys($facets),
    ];

    if (!empty($facets)) {
      $table = [
        '#type' => 'field_ui_table',
        '#header' => $this->getTableHeader(),
        '#regions' => $this->getRegions(),
        '#attributes' => [
          'class' => ['field-ui-overview', 'field-draggable-table'],
          'id' => 'facet-display-overview',
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'field-weight',
          ],
          [
            'action' => 'match',
            'relationship' => 'parent',
            'group' => 'field-parent',
            'subgroup' => 'field-parent',
            'source' => 'field-name',
          ],
          [
            'action' => 'match',
            'relationship' => 'parent',
            'group' => 'field-region',
            'subgroup' => 'field-region',
            'source' => 'field-name',
          ],
        ],
      ];

      $form['#attached']['library'][] = 'search_api_field/drupal.search_api_field';
      $form['refresh_rows'] = [
        '#type' => 'hidden',
      ];
      // Order facets because ['#access'] = FALSE drops weight values,
      // does not update correctly.
      uasort($facets, function (FacetInterface $a, FacetInterface $b) {
        return $a->getWeight() <=> $b->getWeight();
      });

      // Populate facet rows for each region.
      foreach ($facets as $facet_id => $facet) {
        $table[$facet_id] = $this->buildFacetRow($facet, $item, $delta);
      }

      $element['wrapper']['fields'] = $table;
    }

    // Show a select that allows users to choose whether to include all content
    // items, show only unarchived content or show only archived content.
    $element['wrapper']['show_archived'] = [
      '#type' => 'select',
      '#title' => $this->t('Show content'),
      '#options' => [
        'all' => $this->t('All'),
        'archived' => $this->t('Archived'),
        'unarchived' => $this->t('Unarchived'),
      ],
      '#default_value' => $default_values['show_archived'] ?? 'unarchived',
      '#weight' => -12,
    ];

    if (!empty($this->getSetting('query_builder'))) {
      $element['wrapper']['query_builder'] = $this->buildQueryBuilder($item, $delta, $form, $form_state);
    }

    $element['wrapper']['query_presets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Query presets'),
      '#description' => $this->getQueryPresetsDescription(),
      '#default_value' => $default_values['query_presets'] ?? '',
      '#element_validate' => [[$this, 'validateQueryPresets']],
    ];

    $element['wrapper']['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('The number of results to show per page.'),
      '#default_value' => $default_values['limit'] ?? 10,
      '#min' => 1,
    ];

    return $element;
  }

  /**
   * Gets name of the enabled field.
   *
   * @param int $delta
   *   Delta.
   * @param array $element
   *   Element.
   *
   * @return string
   *   Name of enabled field
   */
  protected function getNameOfEnabledField(int $delta, array $element): string {
    $parents = array_merge($element['#field_parents'], [
      $this->fieldDefinition->getName(),
      $delta,
      'enabled',
    ]);

    return array_shift($parents) . '[' . implode('][', $parents) . ']';
  }

  /**
   * Returns the region to which a row belongs.
   *
   * @param array $row
   *   The row element.
   *
   * @return string|null
   *   The region name this row belongs to, or 'hidden' if none is found.
   */
  public static function getRowRegion(array $row): ?string {
    // Check plugin type fields first.
    if (isset($row['plugin']['type']['#value'])) {
      return $row['plugin']['type']['#value'];
    }

    if (isset($row['plugin']['type']) && !is_array($row['plugin']['type'])) {
      return $row['plugin']['type'];
    }

    // Check region fields next.
    if (isset($row['region']['#value'])) {
      return $row['region']['#value'];
    }

    if (isset($row['region']) && !is_array($row['region'])) {
      return $row['region'];
    }

    // Finally, try to extract from CSS classes.
    if (!empty($row['#attributes']['class']) && is_array($row['#attributes']['class'])) {
      foreach ($row['#attributes']['class'] as $class) {
        if (strpos($class, 'region-') === 0) {
          return substr($class, 7);
        }
      }
    }

    // Default to hidden if no region is found.
    return 'hidden';
  }

  /**
   * Returns the headers for the facet table.
   *
   * @return array
   *   An array of column header definitions for the facet table.
   */
  protected function getTableHeader(): array {
    return [
      $this->t('Field'),
      $this->t('Weight'),
      $this->t('Parent'),
      $this->t('Region'),
      ['data' => $this->t('Widget'), 'colspan' => 3],
    ];
  }

  /**
   * Get defined facet regions.
   *
   * @return array
   *   List of facet regions.
   */
  protected function getRegions(): array {
    $facet_regions = $this->fieldDefinition->getSetting('facet_regions');
    $regions = [];
    foreach ($facet_regions as $id => $label) {
      $regions[$id] = [
        'title' => $label,
        'message' => $this->t('No field is displayed.'),
      ];
    }
    $regions['hidden'] = [
      'title' => $this->t('Disabled', [], ['context' => 'Plural']),
      'message' => $this->t('No field is hidden.'),
    ];
    return $regions;
  }

  /**
   * Build draggable facet row.
   *
   * @param \Drupal\facets\Entity\Facet $facet
   *   The facet entity to build a row for.
   * @param \Drupal\search_api_field\Plugin\Field\FieldType\SearchItem $item
   *   The search field item containing stored values.
   * @param int $delta
   *   The delta position in the field.
   *
   * @return array
   *   Render array for the facet row.
   */
  protected function buildFacetRow(Facet $facet, SearchItem $item, int $delta): array {
    $facet_id = $facet->id();
    $label = $facet->label();
    $stored_values = $item->get('value')->getValue();
    $stored_facets = $stored_values['fields'] ?? [];
    $facet_config = $stored_facets[$facet_id] ?? [];

    // Extract facet configuration with defaults.
    $config = [
      'weight' => $facet_config['weight'] ?? $facet->getWeight(),
      'region' => $facet_config['region'] ?? 'hidden',
      'parent' => $facet_config['parent'] ?? '',
    ];

    // Get region options from available regions.
    $region_options = array_map(
      fn($region_info) => $region_info['title'],
      $this->getRegions()
    );

    // Create a unique row ID and CSS classes.
    $row_id = Html::getUniqueId('facet-row-' . $facet_id);
    $region_class = Html::getClass("region-{$config['region']}");

    return [
      '#attributes' => [
        'class' => ['draggable', 'tabledrag-leaf', $region_class],
        'id' => $row_id,
      ],
      '#row_type' => 'field',
      '#region_callback' => [$this, 'getRowRegion'],
      '#js_settings' => [
        'rowHandler' => 'field',
        'defaultPlugin' => $facet_id,
      ],
      'human_name' => [
        '#markup' => $label,
      ],
      'weight' => [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#default_value' => $config['weight'],
        '#attributes' => ['class' => ['field-weight']],
      ],
      'parent_wrapper' => [
        'parent' => [
          '#type' => 'select',
          '#title' => $this->t('Parent for @title', ['@title' => $label]),
          '#title_display' => 'invisible',
          '#options' => $region_options,
          '#default_value' => $config['parent'],
          '#empty_value' => '',
          '#attributes' => ['class' => ['js-field-parent', 'field-parent']],
          '#parents' => [$this->fieldDefinition->getName(), $delta, 'wrapper', 'fields', $facet_id, 'parent'],
        ],
        'hidden_name' => [
          '#type' => 'hidden',
          '#default_value' => $facet_id,
          '#attributes' => ['class' => ['field-name']],
        ],
      ],
      'region' => [
        '#type' => 'select',
        '#title' => $this->t('Region for @title', ['@title' => $label]),
        '#title_display' => 'invisible',
        '#options' => $region_options,
        '#default_value' => $config['region'],
        '#attributes' => ['class' => ['field-region']],
        '#parents' => [$this->fieldDefinition->getName(), $delta, 'wrapper', 'fields', $facet_id, 'region'],
      ],
      'facet_id' => [
        '#type' => 'hidden',
        '#default_value' => $facet_id,
      ],
    ];
  }

  /**
   * Get a list of applicable facets.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   List of facets.
   */
  protected function getFacets(): array {
    $facet_storage = $this->entityTypeManager->getStorage('facets_facet');
    $facets = $facet_storage->loadByProperties([
      'facet_source_id' => 'search_api_field:' . $this->fieldDefinition->id(),
    ]);
    return $facets;
  }

  /**
   * Builds the query builder form interface.
   *
   * @param \Drupal\search_api_field\Plugin\Field\FieldType\SearchItem $item
   *   The value for the specific delta of this field.
   * @param int $delta
   *   The order of this item in the array of sub-elements (0, 1, 2, etc.).
   * @param array $form
   *   The form structure where widgets are being attached to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure for the query builder.
   */
  protected function buildQueryBuilder(SearchItem $item, int $delta, array &$form, FormStateInterface $form_state): array {
    $index = $this->getSearchApiIndex();
    if (!$index) {
      return [];
    }

    // Wrap everything with a container for ajax rebuilds.
    $wrapper_id = Html::getUniqueId('query-builder-wrapper');
    $element = [
      '#type' => 'details',
      '#title' => $this->t('Content filter'),
      '#open' => TRUE,
      '#attributes' => [
        'id' => $wrapper_id,
      ],
    ];

    $parents = $form['#parents'];
    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    if (!isset($field_state['query_builder'][$delta])) {
      $default_value = $item->get('value')->getValue();
      $field_state['query_builder'][$delta] = $default_value['query_builder'] ?? ['filters' => []];
      static::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    $filters = $field_state['query_builder'][$delta]['filters'];
    $element['filters'] = [
      '#type' => 'table',
      '#header' => [
        [
          'data' => $this->t('Filter'),
          'colspan' => 2,
        ],
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('Add a filter to configure it.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];
    // Create a name suffix from the parents to make sure each submit is unique.
    $id_suffix = $parents ? '_' . implode('_', $parents) : '';
    foreach ($filters as $plugin_delta => $plugin_config) {
      if (!is_array($plugin_config) || empty($plugin_config['plugin'])) {
        continue;
      }

      /** @var \Drupal\search_api_field\Plugin\FilterPluginInterface $plugin */
      $plugin = $this->filterPluginManager->createInstance($plugin_config['plugin'], $plugin_config);

      $subform = &$element['filters'][$plugin_delta];
      $subform = [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);

      $element['filters'][$plugin_delta] = [
        'drag_handle' => [
          '#wrapper_attributes' => ['class' => ['tabledrag-handle-cell']],
        ],
        'plugin' => $plugin->buildConfigurationForm($subform, $subform_state),
        'weight' => [
          '#type' => 'weight',
          '#default_value' => $plugin_delta,
          '#attributes' => ['class' => ['table-sort-weight']],
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove filter'),
          '#name' => "remove_filter_{$plugin_delta}{$id_suffix}",
          '#submit' => [[$this, 'submitRemoveFilter']],
          '#ajax' => [
            'callback' => [$this, 'ajaxUpdateQueryBuilder'],
            'wrapper' => $wrapper_id,
          ],
          '#limit_validation_errors' => [],
        ],
        '#attributes' => ['class' => ['draggable']],
      ];
    }

    $options = [];
    foreach ($index->getFields() as $field_id => $field) {
      foreach ($this->filterPluginManager->getDefinitionsForField($field) as $plugin_id => $plugin_definition) {
        $options["{$field_id}:{$plugin_id}"] = $plugin_definition['label'] ?? $field->getLabel();
      }
    }

    $element['add_more'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form__add-more-wrapper']],
    ];
    $element['add_more']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Available filters'),
      '#options' => $options,
      '#required' => FALSE,
    ];
    $element['add_more']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add and configure filter'),
      '#name' => 'add_filter' . $id_suffix,
      '#submit' => [[$this, 'submitAddFilter']],
      '#ajax' => [
        'callback' => [$this, 'ajaxUpdateQueryBuilder'],
        'wrapper' => $wrapper_id,
      ],
      '#limit_validation_errors' => [
        array_merge(
          $parents,
          [$field_name, $delta, 'wrapper', 'query_builder']
        ),
      ],
    ];

    return $element;
  }

  /**
   * Ajax callback to re-render the query builder wrapper.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form portion to render.
   */
  public function ajaxUpdateQueryBuilder(array $form, FormStateInterface $form_state): array {
    $button = $form_state->getTriggeringElement();
    $wrapper_parents = array_slice($button['#array_parents'], 0, array_search('query_builder', $button['#array_parents'], TRUE));
    $element = NestedArray::getValue($form, $wrapper_parents);

    return $element['query_builder'];
  }

  /**
   * Submit handler for the add filter button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitAddFilter(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $wrapper = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    // Extract field id and plugin id from the selected option. Since field ids
    // cannot contain special characters, it's safe to explode on the first
    // colon.
    [$field_id, $plugin_id] = explode(':', $form_state->getValue($wrapper['field']['#parents']), 2);

    // Extract element and widget elements.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -4));
    $widget = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -5));

    $field_name = $widget['#field_name'];
    $parents = $widget['#field_parents'];
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    // Add the selected filter to the list for this specific delta.
    $field_state['query_builder'][$element['#delta']]['filters'][] = [
      'plugin' => $plugin_id,
      'field' => $field_id,
    ];
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Submit handler for the remove filter button.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitRemoveFilter(array $form, FormStateInterface $form_state): void {
    $button = $form_state->getTriggeringElement();
    $filter = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));
    $plugin_delta = end($filter['#array_parents']);

    // Extract element and widget elements.
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -5));
    $widget = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -6));

    $field_name = $widget['#field_name'];
    $parents = $widget['#field_parents'];
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    unset($field_state['query_builder'][$element['#delta']]['filters'][$plugin_delta]);
    static::setWidgetState($parents, $field_name, $form_state, $field_state);

    $form_state->setRebuild();
  }

  /**
   * Gets the search API index configured in the field storage.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The index entity. Null if not found.
   */
  protected function getSearchApiIndex(): ?IndexInterface {
    if ($index_id = $this->fieldDefinition->getSetting('index')) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_id);
      return $index;
    }
    return NULL;
  }

  /**
   * Validates data entered in the query preset field.
   *
   * @param array $element
   *   The query preset element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateQueryPresets(array &$element, FormStateInterface $form_state): void {
    $list = explode("\n", $element['#value']);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');
    $available_fields = $this->getSearchApiIndex()->getFields(TRUE);
    $allowed_operators = ['=', '<>', 'IN', 'NOT IN', '<', '>'];

    foreach ($list as $line) {
      $matches = [];
      if (!preg_match('/([^\|]*)\|([^\|]*)(?:\|(.*))?/', $line, $matches)) {
        $form_state->setError($element, $this->t('Invalid query preset line added: %line.', [
          '%line' => $line,
        ]));
        continue;
      }

      $field = trim($matches[1]);
      if (!isset($available_fields[$field])) {
        $form_state->setError($element, $this->t('Invalid search field specified: %field.', [
          '%field' => $field,
        ]));
      }

      $operator = isset($matches[3]) ? trim($matches[3]) : '=';
      if (!in_array($operator, $allowed_operators, TRUE)) {
        $form_state->setError($element, $this->t('Invalid operator specified: %operator. Allowed operators are @operators.', [
          '%operator' => $operator,
          '@operators' => implode(', ', array_map(function ($op) {
            return "'$op'";
          }, $allowed_operators)),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    if (empty($values)) {
      return $values;
    }

    $field_name = $this->fieldDefinition->getName();
    $field_state = static::getWidgetState($form['#parents'], $field_name, $form_state);
    $widget = NestedArray::getValue($form_state->getCompleteForm(), $field_state['array_parents']);

    $cleaned_values = [];
    foreach ($values as $delta => $value) {
      $processed = [
        'fields' => $this->processFacetFields($value),
        'enabled' => $value['enabled'],
        'query_presets' => $value['wrapper']['query_presets'],
        'show_archived' => $value['wrapper']['show_archived'],
        'limit' => $value['wrapper']['limit'],
      ];

      if (!empty($value['wrapper']['query_builder']['filters'])) {
        $processed['query_builder']['filters'] = $this->processFilters(
          $value['wrapper']['query_builder']['filters'],
          $delta,
          $field_state,
          $widget,
          $form_state
        );
      }

      $cleaned_values[$delta]['value'] = $processed;
    }

    return $cleaned_values;
  }

  /**
   * Process facet fields from form values.
   *
   * @param array $value
   *   The form values containing wrapper fields.
   *
   * @return array
   *   Processed facet fields.
   */
  private function processFacetFields(array $value): array {
    $fields = [];

    if (empty($value['wrapper']['fields'])) {
      return $fields;
    }

    foreach ($value['wrapper']['fields'] as $fn => $field) {
      if (!$this->isFacetField($fn, $field)) {
        continue;
      }

      $facet_id = $this->extractFormValue($field, 'facet_id', $fn);

      $fields[$facet_id] = [
        'weight' => $this->getFieldWeight($field),
        'region' => $this->getFieldRegion($field),
      ];

      $parent = $this->getFieldParent($field);
      if ($parent !== NULL) {
        $fields[$facet_id]['parent'] = $parent;
      }
    }

    return $fields;
  }

  /**
   * Check if the given field is a facet field that should be processed.
   *
   * @param string $field_name
   *   The field name being checked.
   * @param mixed $field
   *   The field data.
   *
   * @return bool
   *   TRUE if this is a facet field that should be processed.
   */
  private function isFacetField(string $field_name, $field): bool {
    // Skip non-array fields and region markers.
    if (strpos($field_name, 'region-') === 0 || !is_array($field)) {
      return FALSE;
    }

    // Check if this is a draggable facet row by verifying key elements.
    return isset($field['facet_id']) ||
      (isset($field['human_name']) && isset($field['weight'])) ||
      (isset($field['plugin']) && isset($field['plugin']['type']));
  }

  /**
   * Extract a form value from either direct value or nested form element.
   *
   * @param array $field
   *   The field array containing form elements.
   * @param string $key
   *   The key to extract from the field.
   * @param mixed $default
   *   Default value to return if the key is not found.
   *
   * @return mixed
   *   The extracted value or default if not found.
   */
  private function extractFormValue(array $field, string $key, $default = NULL): mixed {
    if (array_key_exists($key, $field)) {
      // Direct scalar value.
      if (!is_array($field[$key])) {
        return $field[$key];
      }

      // Look for value in form API keys.
      if (isset($field[$key]['#value'])) {
        return $field[$key]['#value'];
      }
      if (isset($field[$key]['#default_value'])) {
        return $field[$key]['#default_value'];
      }
    }

    return $default;
  }

  /**
   * Get the weight value from a field.
   *
   * @param array $field
   *   The field array.
   *
   * @return int
   *   The weight value, defaulting to 0 if not found.
   */
  private function getFieldWeight(array $field): int {
    return (int) $this->extractFormValue($field, 'weight', 0);
  }

  /**
   * Get the region value from a field.
   *
   * @param array $field
   *   The field array.
   *
   * @return string
   *   The region value, defaulting to 'hidden' if not found.
   */
  private function getFieldRegion(array $field): string {
    // Try plugin type first (Field UI pattern for dragging between regions)
    if (isset($field['plugin']['type'])) {
      $region = $this->extractFormValue($field['plugin'], 'type', NULL);
      if ($region !== NULL) {
        return $region;
      }
    }

    // Then try direct region field.
    $region = $this->extractFormValue($field, 'region', NULL);
    if ($region !== NULL) {
      return $region;
    }

    // Default to hidden.
    return 'hidden';
  }

  /**
   * Get the parent value from a field if it exists.
   *
   * @param array $field
   *   The field array.
   *
   * @return string|null
   *   The parent value, or NULL if not found.
   */
  private function getFieldParent(array $field): ?string {
    // Check the direct parent field.
    if (isset($field['parent'])) {
      return $this->extractFormValue($field, 'parent');
    }

    // Check parent in parent_wrapper.
    if (isset($field['parent_wrapper']['parent'])) {
      return $this->extractFormValue($field['parent_wrapper'], 'parent');
    }

    return NULL;
  }

  /**
   * Process filter values from form submission.
   *
   * @param array $filter_values
   *   The filter values from the form.
   * @param int $delta
   *   The delta position in the field.
   * @param array $field_state
   *   The field state containing widget information.
   * @param array $widget
   *   The form widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Processed filter values.
   */
  private function processFilters(array $filter_values, int $delta, array $field_state, array $widget, FormStateInterface $form_state): array {
    // Re-order values in case JS is not used.
    uasort(
      $filter_values,
      ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']
    );

    $processed_filters = [];
    foreach (array_keys($filter_values) as $plugin_delta) {
      $plugin_config = $field_state['query_builder'][$delta]['filters'][$plugin_delta] ?? NULL;
      if (!is_array($plugin_config) || empty($plugin_config['plugin'])) {
        continue;
      }

      /** @var \Drupal\search_api_field\Plugin\FilterPluginInterface $plugin */
      $plugin = $this->filterPluginManager->createInstance($plugin_config['plugin'], $plugin_config);

      $subform = $widget[$delta]['wrapper']['query_builder']['filters'][$plugin_delta]['plugin'];
      $subform_state = SubformState::createForSubform($subform, $form_state->getCompleteForm(), $form_state);
      $plugin->submitConfigurationForm($subform, $subform_state);

      // Don't specify a key, so that plugins are re-indexed.
      $processed_filters[] = $plugin->getConfiguration();
    }

    return $processed_filters;
  }

  /**
   * Gets the default query presets description.
   *
   * @return array
   *   A render array containing the description.
   */
  protected function getQueryPresetsDescription(): array {
    if (!$index = $this->getSearchApiIndex()) {
      return [];
    }

    $description = [
      '#type' => 'details',
      '#title' => $this->t('Help'),
      '#open' => FALSE,
      'help' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Presets to apply to the query when it is executed. Syntax is %syntax1 or %syntax2, where %field is the field name as configured in the index.', [
            '%syntax1' => 'field|value',
            '%syntax2' => 'field|value|operator',
            '%field' => 'field',
          ]),
        ],
      ],
    ];

    // The description for the presets can vary a lot depending on where the
    // widget is shown. Allow other modules to interact with the description.
    // Dispatch an event to allow other modules to alter the description.
    $event = new AlterQueryPresetsDescriptionEvent($description, $index);
    $this->eventDispatcher->dispatch($event);
    return $event->getDescription();
  }

}
