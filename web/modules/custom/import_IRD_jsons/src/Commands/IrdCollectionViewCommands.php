<?php

namespace Drupal\import_IRD_jsons\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drush\Commands\DrushCommands;

/**
 * Derives a filtered "solutions in collection" view from annotation claims.
 *
 * For a collection whose annotation bundle has already been provisioned by
 * ird:annotate, this command inspects every field_{collection_id}_* FieldConfig
 * on that bundle and emits a complete Drupal Views config entity with:
 *
 *   - Base table   : node_field_data  (annotation nodes as the driving table)
 *   - Hard filter  : type = {collection_id}_annotation
 *   - Hard filter  : og_audience_target_id = {collection_nid}  (if resolvable)
 *   - Relationship : field_innovation → solution node
 *   - Fields       : solution title (linked), solution body/description
 *   - Exposed form : one filter per annotation claim field
 *   - Page display : /collections/{collection_id}/solutions
 *
 * Why annotation nodes as base (not solution nodes)?
 *   The annotation fields (field_{coll}_{key}) live on the annotation bundle.
 *   Driving the query from annotation nodes means every claim filter is a
 *   simple WHERE on the base, with no additional table hops.  Solution data
 *   is pulled via the field_innovation relationship (1:1 per collection).
 *
 * Filter plugin mapping:
 *   string / string_long → Views "string" plugin  (LIKE %value%)
 *   entity_reference → taxonomy_index_tid when target is taxonomy_term,
 *                       numeric (target_id) for other entity types
 *   boolean            → boolean plugin
 *   integer / decimal  → numeric plugin
 *   (anything else)    → string plugin as safe fallback
 *
 * Usage:
 *   drush ird:collection-view car
 *   drush ird:collection-view bp5 --rebuild
 *   drush ird:collection-view taat --dry-run
 *
 * @command ird:collection-view
 */
class IrdCollectionViewCommands extends DrushCommands {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct();
  }

  // ------------------------------------------------------------------
  // Command entry point
  // ------------------------------------------------------------------

  /**
   * Derive a filtered collection-solutions view from annotation claim fields.
   *
   * @command ird:collection-view
   * @param string $collection_id Machine-name slug for the collection (e.g. "car", "bp5").
   * @option rebuild Delete and recreate an existing view of the same id.
   * @option dry-run Report what would be generated without writing anything.
   */
  public function collectionView(
    string $collection_id,
    array $options = ['rebuild' => FALSE, 'dry-run' => FALSE],
  ): void {

    $dry     = (bool) $options['dry-run'];
    $rebuild = (bool) $options['rebuild'];
    $bundle  = $collection_id . '_annotation';

    // ---- Guard: annotation bundle must exist ---------------------------
    if (!NodeType::load($bundle)) {
      $this->logger()->error(
        "Annotation bundle '$bundle' not found. Run 'drush ird:annotate $collection_id ...' first."
      );
      return;
    }

    // ---- Discover annotation claim fields ------------------------------
    $slug   = $this->slug($collection_id);
    $prefix = 'field_' . $slug . '_';

    // loadByProperties() keys results by full config entity ID
    // (e.g. "node.dv_solutions_annotation.field_dv_solutions_keywords"),
    // which contains dots that would corrupt Views filter ids and config keys.
    // Re-key immediately by plain field machine name via $fc->getName().
    /** @var \Drupal\field\Entity\FieldConfig[] $annotation_fields */
    $all_configs = $this->entityTypeManager
      ->getStorage('field_config')
      ->loadByProperties(['entity_type' => 'node', 'bundle' => $bundle]);

    $annotation_fields = [];
    foreach ($all_configs as $fc) {
      if (str_starts_with($fc->getName(), $prefix)) {
        $annotation_fields[$fc->getName()] = $fc;
      }
    }

    $this->logger()->notice(
      count($annotation_fields) . ' annotation claim field(s) found on bundle \'' . $bundle . '\': '
      . implode(', ', array_keys($annotation_fields))
    );

    if ($dry) {
      foreach ($annotation_fields as $fc) {
        $this->logger()->notice(
          '  → filter: ' . $fc->getName()
          . ' (' . $fc->label() . ') [' . $fc->getType() . ']'
        );
      }
      $this->logger()->notice(
        'Dry run — view id would be: views.view.collection_' . $slug . '_solutions'
      );
      return;
    }

    // ---- Resolve collection node id (for og_audience hard filter) ------
    // Try loading by a field_identifier or field_short_name equal to the slug.
    // If nothing resolves we skip the og_audience hard-filter (safe: the type
    // filter alone scopes to this collection since bundles are per-collection).
    $collection_nid = $this->resolveCollectionNid($collection_id); //! Seems to always fail -> $collection_nid is a string (dv_solutions); should be int?
    if ($collection_nid) {
      $this->logger()->notice("Resolved og_audience nid: $collection_nid");
    }
    else {
      $this->logger()->warning(
        "Could not resolve a 'collection' node for id '$collection_id'. "
        . 'og_audience filter will be omitted — the type filter is still collection-scoped.'
      );
    }

    // ---- Build and save view config ------------------------------------
    $view_id     = 'collection_' . $slug . '_solutions';
    $config_name = 'views.view.' . $view_id;

    $existing = $this->configFactory->getEditable($config_name);
    if (!$existing->isNew() && !$rebuild) {
      $this->logger()->warning(
        "View '$view_id' already exists. Use --rebuild to replace it."
      );
      return;
    }

    if (!$existing->isNew() && $rebuild) {
      $existing->delete();
      $this->logger()->notice("Deleted existing view '$view_id'.");
    }

    $config = $this->buildViewConfig(
      $view_id,
      $collection_id,
      $slug,
      $bundle,
      $annotation_fields,
      $collection_nid,
    );

    $this->configFactory->getEditable($config_name)->setData($config)->save();

    // Flush Views caches so the new config is picked up immediately.
    \Drupal::service('cache.discovery')->invalidateAll();
    \Drupal::service('cache.render')->invalidateAll();

    $filter_count = count($annotation_fields);
    $path         = '/collections/' . $collection_id . '/solutions';
    $this->logger()->success(
      "Created view '$view_id' with $filter_count exposed filter(s). "
      . "Path: $path  |  Edit: /admin/structure/views/view/$view_id"
    );
  }

  // ------------------------------------------------------------------
  // View config builder
  // ------------------------------------------------------------------

  /**
   * Build the complete Drupal Views config array.
   *
   * Architecture recap (see class docblock for rationale):
   *   Base         → annotation node_field_data
   *   Relationship → solution via field_innovation (alias: solution_node)
   *   Hard filters → bundle type + optional og_audience
   *   Exposed      → one per annotation claim field (on base, no rel needed)
   *   Fields       → solution title + description (via relationship)
   *
   * @param \Drupal\field\Entity\FieldConfig[] $annotation_fields
   */
  private function buildViewConfig(
    string $view_id,
    string $collection_id,
    string $slug,
    string $bundle,
    array $annotation_fields,
    ?int $collection_nid,
  ): array {

    $label   = ucwords(str_replace(['_', '-'], ' ', $collection_id)) . ' – Innovations';
    $rel_id  = 'solution_node'; // alias for the field_innovation relationship

    // ---- Fields --------------------------------------------------------
    $fields = $this->buildFields($rel_id);

    // ---- Relationships -------------------------------------------------
    $relationships = $this->buildRelationships($rel_id); //! Content: Innovation ??

    // ---- Filters -------------------------------------------------------
    $filters = $this->buildFilters($bundle, $collection_nid, $annotation_fields, $slug);

    // ---- Sorts ---------------------------------------------------------
    $sorts = [
      'created' => [
        'id'            => 'created',
        'table'         => 'node_field_data',
        'field'         => 'created',
        'relationship'  => $rel_id,
        'group_type'    => 'group',
        'admin_label'   => '',
        'plugin_id'     => 'date',
        'order'         => 'DESC',
        'expose'        => ['label' => ''],
        'exposed'       => FALSE,
      ],
    ];

    // ---- Display options (shared Master defaults) ----------------------
    $display_options = [
      'title'         => $label,
      'fields'        => $fields,
      'filters'       => $filters,
      'sorts'         => $sorts,
      'relationships' => $relationships,
      'arguments'     => [],
      'pager'         => [
        'type'    => 'full',
        'options' => [
          'offset'                          => 0,
          'pagination_heading_level'        => 'h4',
          'items_per_page'                  => 20,
          'total_pages'                     => NULL,
          'id'                              => 0,
          'tags'                            => [
            'next'     => 'Next ›',
            'previous' => '‹ Previous',
            'first'    => '« First',
            'last'     => 'Last »',
          ],
          'expose'                          => [
            'items_per_page'              => FALSE,
            'items_per_page_label'        => 'Items per page',
            'items_per_page_options'      => '10, 20, 50',
            'items_per_page_options_all'  => FALSE,
            'items_per_page_options_all_label' => '- All -',
            'offset'                      => FALSE,
            'offset_label'               => 'Offset',
          ],
          'quantity'                        => 9,
        ],
      ],
      'exposed_form'  => [
        'type'    => 'basic',
        'options' => [
          'submit_button'     => 'Apply',
          'reset_button'      => TRUE,
          'reset_button_label'=> 'Reset',
          'exposed_sorts_label' => 'Sort by',
          'expose_sort_order' => TRUE,
          'sort_asc_label'    => 'Asc',
          'sort_desc_label'   => 'Desc',
        ],
      ],
      'access'        => ['type' => 'perm', 'options' => ['perm' => 'access content']],
      'cache'         => ['type' => 'tag',  'options' => []],
      'empty'         => [],
      'header'        => [],
      'footer'        => [],
      'style'         => ['type' => 'tiles', 'options' => ['default_row_class' => TRUE]],
      'row'           => ['type' => 'entity:node', 'options' => [
                              'relationship' => $rel_id,
                              'view_mode' => 'view_mode_tile',
        ]],
      'query'         => ['type' => 'views_query', 'options' => [
        'query_comment'    => '',
        'disable_sql_rewrite' => FALSE,
        'distinct'         => TRUE,  // avoids duplicates when multi-value fields widen rows
        'replica'          => FALSE,
        'query_tags'       => [],
      ]],
      'display_extenders' => [],
      'show_admin_links'  => FALSE,
      'filter_groups'     => ['operator' => 'AND', 'groups' => [1 => 'AND']],
    ];

    // ---- Page display --------------------------------------------------
    $page_display_options = [
      'display_extenders' => [],
      'path'              => 'collections/' . $collection_id . '/solutions',
    ];

    return [
      'langcode'     => 'en',
      'status'       => TRUE,
      'dependencies' => [
        'config' => ['field.field.node.' . $bundle . '.field_innovation'],
        'module' => ['node', 'user', 'views', 'text', 'joinup_tiles'],
      ],
      'id'          => $view_id,
      'label'       => $label,
      'module'      => 'views',
      'description' => 'Auto-generated by ird:collection-view. Solutions in collection \''
                       . $collection_id . '\' with annotation claim filters.',
      'tag'         => 'ird',
      'base_table'  => 'node_field_data',
      'base_field'  => 'nid',
      'display'     => [
        'default' => [
          'id'             => 'default',
          'display_title'  => 'Master',
          'display_plugin' => 'default',
          'position'       => 0,
          'display_options'=> $display_options,
          'cache_metadata' => [
            'max-age'  => -1,
            'contexts' => ['languages:language_interface', 'url.query_args', 'user.permissions'],
            'tags'     => [],
          ],
        ],
        'page_1' => [
          'id'             => 'page_1',
          'display_title'  => 'Page',
          'display_plugin' => 'page',
          'position'       => 1,
          'display_options'=> $page_display_options,
          'cache_metadata' => [
            'max-age'  => -1,
            'contexts' => ['languages:language_interface', 'url.query_args', 'user.permissions'],
            'tags'     => [],
          ],
        ],
      ],
    ];
  }

  // ------------------------------------------------------------------
  // Sub-builders
  // ------------------------------------------------------------------

  /**
   * Build the fields sub-array for the solution display.
   *
   * Fields included:
   *   nid (hidden)  — safe base-table field; useful as token in alter exprs.
   *   title         — node_field_data base field, always registered in Views.
   *   abstract      — string(1); short teaser confirmed on solution bundle.
   *   description   — text_long(1); full text, trimmed to 600 chars.
   *
   * All four fields exist on the solution bundle (confirmed via field:info).
   * Because their tables (node__abstract, node__description) are registered
   * in Views data for this entity type, EntityField->getEntityType() can
   * safely trace through the field_innovation relationship to resolve 'node'.
   * Fields from bundles that do NOT exist on the target (e.g. node__body here)
   * must NOT be added here — they crash with InvalidArgumentException at
   * ViewsData->get() because their table is absent from the registered data.
   */
  private function buildFields(string $rel_id): array {
    return [
      // nid (hidden) — useful as a token in alter expressions and as a sort key.
      'nid' => [
        'id'            => 'nid',
        'table'         => 'node_field_data',
        'field'         => 'nid',
        'relationship'  => $rel_id, //! solution_node exists?
        'group_type'    => 'group',
        'admin_label'   => '',
        'entity_type'   => 'node',
        'entity_field'  => 'nid',
        'plugin_id'     => 'field',
        'label'         => 'Nid',
        'exclude'       => TRUE,          // hidden; available as token
        'alter'         => $this->defaultAlter(),
        'element_type'  => '',
        'element_class' => '',
        'element_label_type'    => '',
        'element_label_class'   => '',
        'element_label_colon'   => TRUE,
        'element_wrapper_type'  => '',
        'element_wrapper_class' => '',
        'element_default_classes' => TRUE,
        'empty'           => '',
        'hide_empty'      => FALSE,
        'empty_zero'      => FALSE,
        'hide_alter_empty'=> TRUE,
        'click_sort_column' => 'value',
        'type'            => 'number_integer',
        'settings'        => [],
        'group_column'    => 'value',
        'group_columns'   => [],
        'group_rows'      => TRUE,
        'delta_limit'     => 0,
        'delta_offset'    => 0,
        'delta_reversed'  => FALSE,
        'delta_first_last'=> FALSE,
        'multi_type'      => 'separator',
        'separator'       => ', ',
        'field_api_classes' => FALSE,
      ],
      // Title — always on node_field_data, safe through any relationship.
      'title' => [
        'id'            => 'title',
        'table'         => 'node_field_data',
        'field'         => 'title',
        'relationship'  => $rel_id,
        'group_type'    => 'group',
        'admin_label'   => '',
        'entity_type'   => 'node',
        'entity_field'  => 'title',
        'plugin_id'     => 'field',
        'label'         => '',
        'exclude'       => FALSE,
        'alter'         => $this->defaultAlter(),
        'element_type'  => '',
        'element_class' => '',
        'element_label_type'    => '',
        'element_label_class'   => '',
        'element_label_colon'   => FALSE,
        'element_wrapper_type'  => '',
        'element_wrapper_class' => '',
        'element_default_classes' => TRUE,
        'empty'           => '',
        'hide_empty'      => FALSE,
        'empty_zero'      => FALSE,
        'hide_alter_empty'=> TRUE,
        'click_sort_column' => 'value',
        'type'            => 'string',
        'settings'        => ['link_to_entity' => TRUE],
        'group_column'    => 'value',
        'group_columns'   => [],
        'group_rows'      => TRUE,
        'delta_limit'     => 0,
        'delta_offset'    => 0,
        'delta_reversed'  => FALSE,
        'delta_first_last'=> FALSE,
        'multi_type'      => 'separator',
        'separator'       => ', ',
        'field_api_classes' => FALSE,
      ],
      // Abstract — string(1), short teaser shown in listing cards.
      // 'abstract' => [
      //   'id'            => 'abstract',
      //   'table'         => 'node__abstract',
      //   'field'         => 'abstract',
      //   'relationship'  => $rel_id,
      //   'group_type'    => 'group',
      //   'admin_label'   => '',
      //   'entity_type'   => 'node',
      //   'entity_field'  => 'abstract',
      //   'plugin_id'     => 'field',
      //   'label'         => '',
      //   'exclude'       => FALSE,
      //   'alter'         => $this->defaultAlter(),
      //   'element_type'  => '',
      //   'element_class' => '',
      //   'element_label_type'    => '',
      //   'element_label_class'   => '',
      //   'element_label_colon'   => FALSE,
      //   'element_wrapper_type'  => '',
      //   'element_wrapper_class' => '',
      //   'element_default_classes' => TRUE,
      //   'empty'           => '',
      //   'hide_empty'      => TRUE,
      //   'empty_zero'      => FALSE,
      //   'hide_alter_empty'=> TRUE,
      //   'click_sort_column' => 'value',
      //   'type'            => 'string',
      //   'settings'        => ['link_to_entity' => FALSE],
      //   'group_column'    => 'value',
      //   'group_columns'   => [],
      //   'group_rows'      => TRUE,
      //   'delta_limit'     => 0,
      //   'delta_offset'    => 0,
      //   'delta_reversed'  => FALSE,
      //   'delta_first_last'=> FALSE,
      //   'multi_type'      => 'separator',
      //   'separator'       => ', ',
      //   'field_api_classes' => FALSE,
      // ],
      // // Description — text_long(1), trimmed fallback when abstract is empty.
      // 'description' => [
      //   'id'            => 'description',
      //   'table'         => 'node__description',
      //   'field'         => 'description',
      //   'relationship'  => $rel_id,
      //   'group_type'    => 'group',
      //   'admin_label'   => '',
      //   'entity_type'   => 'node',
      //   'entity_field'  => 'description',
      //   'plugin_id'     => 'field',
      //   'label'         => '',
      //   'exclude'       => FALSE,
      //   'alter'         => $this->defaultAlter(),
      //   'element_type'  => '',
      //   'element_class' => '',
      //   'element_label_type'    => '',
      //   'element_label_class'   => '',
      //   'element_label_colon'   => FALSE,
      //   'element_wrapper_type'  => '',
      //   'element_wrapper_class' => '',
      //   'element_default_classes' => TRUE,
      //   'empty'           => '',
      //   'hide_empty'      => TRUE,
      //   'empty_zero'      => FALSE,
      //   'hide_alter_empty'=> TRUE,
      //   'click_sort_column' => 'value',
      //   'type'            => 'text_trimmed',
      //   'settings'        => ['trim_length' => 600],
      //   'group_column'    => 'value',
      //   'group_columns'   => [],
      //   'group_rows'      => TRUE,
      //   'delta_limit'     => 0,
      //   'delta_offset'    => 0,
      //   'delta_reversed'  => FALSE,
      //   'delta_first_last'=> FALSE,
      //   'multi_type'      => 'separator',
      //   'separator'       => ', ',
      //   'field_api_classes' => FALSE,
      // ],
    ];
  }

  /**
   * Build the relationships sub-array.
   *
   * field_innovation on the annotation (base) node is an entity_reference
   * pointing to the solution.  Views surfaces this as a standard relationship
   * on the node__field_innovation table joining to node_field_data on nid.
   */
  private function buildRelationships(string $rel_id): array {
    return [
      $rel_id => [
        'id'           => $rel_id,
        'table'        => 'node__field_innovation',
        'field'        => 'field_innovation_target_id',
        'relationship' => 'none',
        'group_type'   => 'group',
        'admin_label'  => '',
        'plugin_id'    => 'standard',
        'label'        => 'Solution (via field_innovation)',
        'required'     => TRUE,
      ],
    ];
  }

  /**
   * Build the filters sub-array.
   *
   * Order:
   *   1. Hard filter  – annotation bundle type (no relationship, base table)
   *   2. Hard filter  – og_audience nid (if resolvable)
   *   3. Exposed filters – one per annotation claim field
   *
   * @param \Drupal\field\Entity\FieldConfig[] $annotation_fields
   */
  private function buildFilters(
    string $bundle,
    ?int $collection_nid,
    array $annotation_fields,
    string $slug,
  ): array {

    $filters = [];

    // 1. Hard: annotation node bundle type.
    $filters['type'] = [
      'id'           => 'type',
      'table'        => 'node_field_data',
      'field'        => 'type',
      'relationship' => 'none',
      'group_type'   => 'group',
      'admin_label'  => '',
      'plugin_id'    => 'bundle',
      'operator'     => 'in',
      'value'        => [$bundle => $bundle],
      'group'        => 1,
      'exposed'      => FALSE,
      'expose'       => $this->defaultExpose(),
      'is_grouped'   => FALSE,
      'group_info'   => $this->defaultGroupInfo(),
    ];

    // 2. Hard: og_audience scoped to specific collection node.
    if ($collection_nid) {
      $filters['og_audience_target_id'] = [
        'id'           => 'og_audience_target_id',
        'table'        => 'node__og_audience',
        'field'        => 'og_audience_target_id',
        'relationship' => 'none',
        'group_type'   => 'group',
        'admin_label'  => '',
        'plugin_id'    => 'numeric',
        'operator'     => '=',
        'value'        => ['value' => (string) $collection_nid, 'min' => '', 'max' => ''],
        'group'        => 1,
        'exposed'      => FALSE,
        'expose'       => $this->defaultExpose(),
        'is_grouped'   => FALSE,
        'group_info'   => $this->defaultGroupInfo(),
      ];
    }

    // 3. Exposed: one per annotation claim field.
    foreach ($annotation_fields as $field_name => $fc) {
      $filter = $this->buildFieldFilter($field_name, $fc, $slug);
      if ($filter !== NULL) {
        $filters[$filter['id']] = $filter;
      }
    }

    return $filters;
  }

  /**
   * Build a single exposed filter definition for an annotation claim field.
   *
   * The filter identifier (URL query key) strips the collection prefix so
   * URLs stay readable: ?technologies=drone rather than ?car_technologies=drone.
   *
   * Returns NULL for field types with no sensible filter plugin.
   */
  private function buildFieldFilter(
    string $field_name,
    FieldConfig $fc,
    string $slug,
  ): ?array {

    $type         = $fc->getType();
    $human_label  = (string) $fc->label();
    // Strip collection prefix from the exposed identifier: field_car_technologies → technologies.
    $identifier   = preg_replace('/^field_' . preg_quote($slug, '/') . '_/', '', $field_name);
    $filter_table = 'node__' . $field_name;

    $base = [
      'relationship' => 'none', // annotation claim fields are on the base table
      'group_type'   => 'group',
      'admin_label'  => '',
      'group'        => 1,
      'exposed'      => TRUE,
      'is_grouped'   => FALSE,
      'group_info'   => $this->defaultGroupInfo(),
    ];

    // ---- string / string_long → contains filter ----------------------
    if (in_array($type, ['string', 'string_long', 'text', 'text_long'], TRUE)) {
      $filter_id = $field_name . '_value';
      return $base + [
        'id'        => $filter_id,
        'table'     => $filter_table,
        'field'     => $field_name . '_value',
        'plugin_id' => 'string',
        'operator'  => 'contains',
        'value'     => '',
        'expose'    => [
          'operator_id'      => $filter_id . '_op',
          'label'            => $human_label,
          'description'      => '',
          'use_operator'     => FALSE,
          'operator'         => $filter_id . '_op',
          'operator_limit_selection' => FALSE,
          'operator_list'    => [],
          'identifier'       => $identifier,
          'required'         => FALSE,
          'remember'         => FALSE,
          'multiple'         => FALSE,
          'remember_roles'   => ['authenticated' => 'authenticated'],
          'placeholder'      => '',
        ],
      ];
    }

    // ---- entity_reference → taxonomy or generic numeric ---------------
    if ($type === 'entity_reference') {
      $target_type = $fc->getSetting('target_type');
      $handler_settings = $fc->getSetting('handler_settings') ?? [];
      $target_bundles   = $handler_settings['target_bundles'] ?? [];
      $filter_id = $field_name . '_target_id';

      if ($target_type === 'taxonomy_term' && !empty($target_bundles)) {
        // taxonomy_index_tid gives a nice select/checkboxes with vocab context.
        $vid = array_key_first($target_bundles);
        return $base + [
          'id'        => $filter_id,
          'table'     => $filter_table,
          'field'     => $field_name . '_target_id',
          'plugin_id' => 'taxonomy_index_tid',
          'operator'  => 'or',
          'value'     => [],
          'vid'       => $vid,
          'type'      => 'select',
          'hierarchy' => FALSE,
          'depth'     => 0,
          'expose'    => [
            'operator_id'      => $filter_id . '_op',
            'label'            => $human_label,
            'description'      => '',
            'use_operator'     => FALSE,
            'operator'         => $filter_id . '_op',
            'operator_limit_selection' => FALSE,
            'operator_list'    => [],
            'identifier'       => $identifier,
            'required'         => FALSE,
            'remember'         => FALSE,
            'multiple'         => TRUE,
            'remember_roles'   => ['authenticated' => 'authenticated'],
            'reduce'           => FALSE,
          ],
          'error_message' => TRUE,
        ];
      }

      // Generic entity_reference (e.g. node) → numeric target_id filter.
      return $base + [
        'id'        => $filter_id,
        'table'     => $filter_table,
        'field'     => $field_name . '_target_id',
        'plugin_id' => 'numeric',
        'operator'  => '=',
        'value'     => ['value' => '', 'min' => '', 'max' => ''],
        'expose'    => [
          'operator_id'   => $filter_id . '_op',
          'label'         => $human_label,
          'description'   => '',
          'use_operator'  => FALSE,
          'operator'      => $filter_id . '_op',
          'identifier'    => $identifier,
          'required'      => FALSE,
          'remember'      => FALSE,
          'multiple'      => FALSE,
          'remember_roles'=> ['authenticated' => 'authenticated'],
          'placeholder'   => '',
          'min_placeholder' => '',
          'max_placeholder' => '',
        ],
      ];
    }

    // ---- boolean -------------------------------------------------------
    if ($type === 'boolean') {
      $filter_id = $field_name . '_value';
      return $base + [
        'id'        => $filter_id,
        'table'     => $filter_table,
        'field'     => $field_name . '_value',
        'plugin_id' => 'boolean',
        'operator'  => '=',
        'value'     => 'All',
        'expose'    => [
          'operator_id'  => '',
          'label'        => $human_label,
          'description'  => '',
          'use_operator' => FALSE,
          'operator'     => '',
          'identifier'   => $identifier,
          'required'     => FALSE,
          'remember'     => FALSE,
          'multiple'     => FALSE,
          'remember_roles' => ['authenticated' => 'authenticated'],
        ],
      ];
    }

    // ---- integer / decimal / float ------------------------------------
    if (in_array($type, ['integer', 'decimal', 'float'], TRUE)) {
      $filter_id = $field_name . '_value';
      return $base + [
        'id'        => $filter_id,
        'table'     => $filter_table,
        'field'     => $field_name . '_value',
        'plugin_id' => 'numeric',
        'operator'  => '=',
        'value'     => ['value' => '', 'min' => '', 'max' => ''],
        'expose'    => [
          'operator_id'      => $filter_id . '_op',
          'label'            => $human_label,
          'description'      => '',
          'use_operator'     => TRUE,
          'operator'         => $filter_id . '_op',
          'operator_limit_selection' => FALSE,
          'operator_list'    => [],
          'identifier'       => $identifier,
          'required'         => FALSE,
          'remember'         => FALSE,
          'multiple'         => FALSE,
          'remember_roles'   => ['authenticated' => 'authenticated'],
          'placeholder'      => '',
          'min_placeholder'  => '',
          'max_placeholder'  => '',
        ],
      ];
    }

    // ---- Fallback: string contains ------------------------------------
    // Handles link, telephone, email, and anything else with a _value column.
    $filter_id = $field_name . '_value';
    return $base + [
      'id'        => $filter_id,
      'table'     => $filter_table,
      'field'     => $field_name . '_value',
      'plugin_id' => 'string',
      'operator'  => 'contains',
      'value'     => '',
      'expose'    => [
        'operator_id'   => $filter_id . '_op',
        'label'         => $human_label,
        'description'   => '',
        'use_operator'  => FALSE,
        'operator'      => $filter_id . '_op',
        'operator_limit_selection' => FALSE,
        'operator_list' => [],
        'identifier'    => $identifier,
        'required'      => FALSE,
        'remember'      => FALSE,
        'multiple'      => FALSE,
        'remember_roles'=> ['authenticated' => 'authenticated'],
        'placeholder'   => '',
      ],
    ];
  }

  // ------------------------------------------------------------------
  // Helpers
  // ------------------------------------------------------------------

  /**
   * Try to find the nid of the collection node for this collection_id.
   *
   * Strategy (each step skipped gracefully if the field does not exist):
   *   1. Any field named field_identifier / field_short_name / field_collection_id
   *      whose value matches the collection_id slug.
   *   2. Node title exact-match (last resort).
   *
   * loadByProperties() throws a \Drupal\Core\Database\DatabaseExceptionWrapper
   * (via Tables::ensureEntityTable) when the property name is not a known base
   * field or an installed field_config on this bundle.  We therefore check
   * FieldConfig existence before including a candidate in the query, and wrap
   * the whole thing in a try/catch so an unexpected schema variation never
   * surfaces as a fatal error — the og_audience filter is optional.
   */
  private function resolveCollectionNid(string $collection_id): ?int {
    $storage = $this->entityTypeManager->getStorage('node');

    // Only query fields that are actually installed on the 'collection' bundle.
    $installed = array_map(
      static fn($fc) => $fc->getName(),
      $this->entityTypeManager
        ->getStorage('field_config')
        ->loadByProperties(['entity_type' => 'node', 'bundle' => 'collection'])
    );

    foreach (['field_identifier', 'field_short_name', 'field_collection_id'] as $candidate) {
      if (!in_array($candidate, $installed, TRUE)) {
        continue;
      }
      try {
        $results = $storage->loadByProperties([
          'type'     => 'collection',
          $candidate => $collection_id,
        ]);
        if ($results) {
          return (int) reset($results)->id();
        }
      }
      catch (\Exception $e) {
        // Field exists in config but schema is out of sync — skip silently.
        $this->logger()->debug("resolveCollectionNid: skipping $candidate — " . $e->getMessage());
      }
    }

    // Last resort: match by node title (label), which is always a base field.
    try {
      $results = $storage->loadByProperties([
        'type'  => 'collection',
        'title' => $collection_id,
      ]);
      if ($results) {
        return (int) reset($results)->id();
      }
    }
    catch (\Exception $e) {
      $this->logger()->debug('resolveCollectionNid: title fallback failed — ' . $e->getMessage());
    }

    return NULL;
  }

  private function slug(string $s): string {
    return preg_replace('/[^a-z0-9_]/', '_', strtolower($s));
  }

  /** Default alter array shared by all field definitions. */
  private function defaultAlter(): array {
    return [
      'alter_text'     => FALSE, 'text'          => '',
      'make_link'      => FALSE, 'path'          => '',
      'absolute'       => FALSE, 'external'      => FALSE,
      'replace_spaces' => FALSE, 'path_case'     => 'none',
      'trim_whitespace'=> FALSE, 'alt'           => '',
      'rel'            => '',    'link_class'    => '',
      'prefix'         => '',    'suffix'        => '',
      'target'         => '',    'nl2br'         => FALSE,
      'max_length'     => 0,     'word_boundary' => TRUE,
      'ellipsis'       => TRUE,  'more_link'     => FALSE,
      'more_link_text' => '',    'more_link_path'=> '',
      'strip_tags'     => FALSE, 'trim'          => FALSE,
      'preserve_tags'  => '',    'html'          => FALSE,
    ];
  }

  /** Empty expose skeleton for hard (non-exposed) filters. */
  private function defaultExpose(): array {
    return [
      'operator_id'      => '',
      'label'            => '',
      'description'      => '',
      'use_operator'     => FALSE,
      'operator'         => '',
      'operator_limit_selection' => FALSE,
      'operator_list'    => [],
      'identifier'       => '',
      'required'         => FALSE,
      'remember'         => FALSE,
      'multiple'         => FALSE,
      'remember_roles'   => ['authenticated' => 'authenticated'],
      'min_placeholder'  => '',
      'max_placeholder'  => '',
      'placeholder'      => '',
    ];
  }

  /** Empty group_info skeleton for non-grouped filters. */
  private function defaultGroupInfo(): array {
    return [
      'label'                => '',
      'description'          => '',
      'identifier'           => '',
      'optional'             => TRUE,
      'widget'               => 'select',
      'multiple'             => FALSE,
      'remember'             => FALSE,
      'default_group'        => 'All',
      'default_group_multiple' => [],
      'group_items'          => [],
    ];
  }
}
