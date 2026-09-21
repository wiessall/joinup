<?php

namespace Drupal\import_ird_jsons\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * Renders claim_facet triples as grouped per-property dropdowns.
 *
 * The claim_facet Solr field contains concatenated values like
 * "stf::awards::Yes", "stf::country::Kenya". This widget groups them by
 * property and renders one <select> per property.
 *
 * Because AdjustGroupContentSubscriber scopes the Solr query to the
 * current collection's solutions, only that collection's triples appear
 * in the facet results. No client-side collection filtering is needed.
 *
 * Markup is delegated to the claim_facets_grouped theme hook so themes can
 * override it. Ordering and grouping come from widget configuration rather
 * than a fixed alphabetical sort, so the arrangement is editable at
 * /admin/config/search/facets/{facet}/edit.
 *
 * Selects are multi-value and opt into the Chosen module via '#chosen'. Each
 * option carries its own Facets toggle URL in data-urls;
 * js/claim-facet-dropdown.js diffs the selection against data-selected to
 * find which value changed and follows that one URL. This avoids
 * reconstructing Facets' f[] query parameters client-side.
 *
 * URL format: ?f[0]=claim_facet:stf::awards::Yes
 * The full triple is the facet value and the Facets module handles encoding.
 *
 * @FacetsWidget(
 *   id = "grouped_claim_dropdown",
 *   label = @Translation("Grouped claim dropdowns"),
 *   description = @Translation("Splits collection::property::value triples into per-property multi-select dropdowns, arranged into configurable sections."),
 * )
 */
class GroupedClaimDropdownWidget extends WidgetPluginBase {

  /**
   * The placeholder the STI Portal uses on its faceted search filters.
   */
  private const DEFAULT_PLACEHOLDER = 'Click to see options, or start typing to search. You can select multiple values.';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'show_numbers' => TRUE,
      'multiple' => TRUE,
      'use_chosen' => TRUE,
      'placeholder' => self::DEFAULT_PLACEHOLDER,
      'sections' => '',
      'ungrouped_label' => 'Other filters',
      'hide_ungrouped' => FALSE,
      'open_sections' => 'first',
      'exclude' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet): array {
    $config = $this->getConfiguration();

    $form['show_numbers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show counts'),
      '#default_value' => $config['show_numbers'],
    ];

    $form['multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow several values per property'),
      '#description' => $this->t('Requires the facet query operator to be OR, which it is. Unticking reverts to single-value dropdowns.'),
      '#default_value' => $config['multiple'],
    ];

    $form['use_chosen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enhance with Chosen'),
      '#description' => $this->t('Adds a searchable tag-style control. Needs the Chosen module enabled; the FAO theme already ships the matching CSS and the faceted-search-dropdown.js tree enhancement. Harmless if Chosen is absent — the selects just render plain.'),
      '#default_value' => $config['use_chosen'],
    ];

    $form['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder text'),
      '#description' => $this->t('Shown in the empty control. Chosen reads this from data-placeholder.'),
      '#default_value' => $config['placeholder'],
      '#maxlength' => 255,
    ];

    $form['sections'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sections and order'),
      '#description' => $this->t('One section per line, as <em>Label|property_slug,property_slug</em>. Sections appear in the order listed, and properties in the order listed within each section. Property slugs are the middle segment of the triple, e.g. <code>benefitting_country</code>. Leave empty for a single alphabetical list.'),
      '#default_value' => $config['sections'],
      '#rows' => 8,
      '#placeholder' => "Geography|country,benefitting_country\nImpact|greenhouse_gasses_impact,water_use_impact,land_use_impact,biodiversity_impact\nRecognition|award_applied_for,award_year,level_recognized",
    ];

    $form['ungrouped_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for unlisted properties'),
      '#default_value' => $config['ungrouped_label'],
    ];

    $form['hide_ungrouped'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide properties not named in a section'),
      '#description' => $this->t('Curates the filter set without editing the facet or the Solr processor.'),
      '#default_value' => $config['hide_ungrouped'],
    ];

    $form['open_sections'] = [
      '#type' => 'select',
      '#title' => $this->t('Sections expanded by default'),
      '#options' => [
        'first' => $this->t('First section only'),
        'all' => $this->t('All sections'),
        'none' => $this->t('None'),
        'active' => $this->t('Sections containing an active filter'),
      ],
      '#default_value' => $config['open_sections'],
    ];

    $form['exclude'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Properties to exclude'),
      '#description' => $this->t('One property slug per line, for values that are indexed but not meaningful as filters, e.g. <code>latitude</code>.'),
      '#default_value' => $config['exclude'],
      '#rows' => 3,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet): array {
    $config = $this->getConfiguration();
    $exclude = $this->parseList($config['exclude'] ?? '');

    $grouped = [];

    foreach ($facet->getResults() as $result) {
      $raw = $result->getRawValue();
      $parts = explode('::', $raw, 3);
      if (count($parts) !== 3) {
        continue;
      }

      [, $property, $value] = $parts;

      if (in_array($property, $exclude, TRUE)) {
        continue;
      }

      $grouped[$property]['label'] = ucwords(str_replace('_', ' ', $property));
      $grouped[$property]['items'][] = [
        'raw' => $raw,
        'value' => $value,
        'count' => $result->getCount(),
        'active' => $result->isActive(),
        'url' => $result->getUrl(),
      ];
    }

    if ($grouped === []) {
      return [];
    }

    $filters = [];
    foreach ($grouped as $slug => $group) {
      $filters[$slug] = $this->buildFilter($slug, $group, $config);
    }

    $sections = $this->arrangeSections($filters, $config);

    $active_total = 0;
    foreach ($sections as $section) {
      $active_total += $section['active_count'];
    }

    return [
      '#theme' => 'claim_facets_grouped',
      '#sections' => $sections,
      '#facet_id' => $facet->id(),
      '#active_count' => $active_total,
      '#attached' => [
        'library' => ['import_ird_jsons/claim_facet_dropdown'],
      ],
      '#cache' => [
        'contexts' => ['url.query_args'],
      ],
    ];
  }

  /**
   * Builds a single property dropdown.
   */
  private function buildFilter(string $slug, array $group, array $config): array {
    $multiple = (bool) $config['multiple'];
    $show_numbers = (bool) $config['show_numbers'];

    $options = [];
    $urls = [];
    $selected = [];

    // A single-value control needs an explicit empty option; a multi-value
    // one shows the placeholder instead.
    if (!$multiple) {
      $options[''] = $this->t('- Any @label -', ['@label' => $group['label']]);
    }

    foreach ($group['items'] as $item) {
      $label = $item['value'];
      if ($show_numbers) {
        $label .= ' (' . $item['count'] . ')';
      }
      $options[$item['raw']] = $label;
      $urls[$item['raw']] = $item['url'] instanceof Url
        ? $item['url']->toString()
        : (string) $item['url'];

      if ($item['active']) {
        $selected[] = $item['raw'];
      }
    }

    // Each active item's own URL toggles it off, which is standard Facets
    // behaviour, so the first one doubles as the single-select reset target.
    if (!$multiple && $selected !== [] && isset($urls[$selected[0]])) {
      $urls[''] = $urls[$selected[0]];
    }

    $id = 'claim-facet-' . $slug;

    $classes = ['claim-facet-select'];
    if (!empty($config['use_chosen'])) {
      // js/throbber.js in the FAO theme keys on .chosen-select, and the
      // Chosen module's default jQuery selector matches it too.
      $classes[] = 'chosen-select';
    }

    $select = [
      '#type' => 'select',
      '#options' => $options,
      '#multiple' => $multiple,
      '#default_value' => $multiple ? $selected : ($selected[0] ?? ''),
      '#value' => $multiple ? $selected : ($selected[0] ?? ''),
      '#attributes' => [
        'id' => $id,
        'class' => $classes,
        // Read by js/claim-facet-dropdown.js.
        'data-urls' => json_encode($urls),
        'data-selected' => json_encode($selected),
        'data-placeholder' => $config['placeholder'] ?: self::DEFAULT_PLACEHOLDER,
      ],
    ];

    if (!empty($config['use_chosen'])) {
      // Documented opt-in for the Chosen module. Ignored when Chosen is not
      // installed, so this stays safe either way.
      $select['#chosen'] = TRUE;
    }

    return [
      'slug' => $slug,
      'label' => $group['label'],
      'id' => $id,
      'active' => $selected !== [],
      'active_values' => $selected,
      'option_count' => count($group['items']),
      'multiple' => $multiple,
      'select' => $select,
    ];
  }

  /**
   * Distributes the filters into ordered sections per configuration.
   */
  private function arrangeSections(array $filters, array $config): array {
    $definitions = $this->parseSections($config['sections'] ?? '');

    if ($definitions === []) {
      uasort($filters, fn($a, $b) => strcmp($a['label'], $b['label']));
      return [
        $this->section('all', (string) $this->t('Filters'), array_values($filters)),
      ];
    }

    $sections = [];
    $placed = [];

    foreach ($definitions as $index => $definition) {
      $chosen = [];
      foreach ($definition['slugs'] as $slug) {
        if (isset($filters[$slug])) {
          $chosen[] = $filters[$slug];
          $placed[$slug] = TRUE;
        }
      }
      // Skip sections whose properties returned nothing for this collection,
      // rather than rendering an empty accordion item.
      if ($chosen !== []) {
        $sections[] = $this->section('s' . $index, $definition['label'], $chosen);
      }
    }

    if (empty($config['hide_ungrouped'])) {
      $rest = array_diff_key($filters, $placed);
      if ($rest !== []) {
        uasort($rest, fn($a, $b) => strcmp($a['label'], $b['label']));
        $sections[] = $this->section(
          'other',
          (string) ($config['ungrouped_label'] ?: $this->t('Other filters')),
          array_values($rest)
        );
      }
    }

    return $this->applyOpenState($sections, (string) ($config['open_sections'] ?? 'first'));
  }

  /**
   * Assembles one section structure.
   */
  private function section(string $key, string $label, array $filters): array {
    $active = 0;
    foreach ($filters as $filter) {
      $active += count($filter['active_values']);
    }

    return [
      'id' => 'claim-facet-section-' . $key,
      'label' => $label,
      'filters' => $filters,
      'active_count' => $active,
      'open' => FALSE,
    ];
  }

  /**
   * Marks which sections start expanded.
   */
  private function applyOpenState(array $sections, string $mode): array {
    foreach ($sections as $i => $section) {
      $sections[$i]['open'] = match ($mode) {
        'all' => TRUE,
        'none' => FALSE,
        'active' => $section['active_count'] > 0,
        default => $i === 0,
      };
    }

    // Always reveal a section holding an active filter, otherwise an applied
    // filter is invisible until the user hunts for it.
    if (in_array($mode, ['first', 'none'], TRUE)) {
      foreach ($sections as $i => $section) {
        if ($section['active_count'] > 0) {
          $sections[$i]['open'] = TRUE;
        }
      }
    }

    return $sections;
  }

  /**
   * Parses the sections textarea into label/slugs pairs.
   */
  private function parseSections(string $raw): array {
    $out = [];

    foreach (preg_split('/\R/', $raw) as $line) {
      $line = trim($line);
      if ($line === '' || !str_contains($line, '|')) {
        continue;
      }

      [$label, $slugs] = explode('|', $line, 2);
      $label = trim($label);
      $slugs = array_values(array_filter(array_map('trim', explode(',', $slugs))));

      if ($label !== '' && $slugs !== []) {
        $out[] = ['label' => $label, 'slugs' => $slugs];
      }
    }

    return $out;
  }

  /**
   * Parses a newline- or comma-separated list of slugs.
   */
  private function parseList(string $raw): array {
    $parts = preg_split('/[\R,]+/', $raw) ?: [];
    return array_values(array_filter(array_map('trim', $parts)));
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyRequired($name, $type): bool {
    if ($name === 'url_processor_handler' && $type === 'processors') {
      return TRUE;
    }
    return FALSE;
  }

}
