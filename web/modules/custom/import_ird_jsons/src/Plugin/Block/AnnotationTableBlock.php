<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders field_claims paragraphs on a solution as FAO-styled tables.
 *
 * Claims are grouped twice: first by claimer (field_claimed_by, which is
 * single-valued), then by asserted property (field_claim_property). Multiple
 * claims of the same property by the same claimer collapse into one row with
 * comma-separated values, so a harvester that emits one paragraph per SDG
 * produces a single "SDGs Addressed" row rather than five.
 *
 * Markup follows themes/custom/fao/templates/content/node--digital-asset.html.twig:
 * `table table-bordered` with the property in a row-scoped
 * `<th class="bg-gray-light">` rather than a thead-driven Property/Value table.
 *
 * Two layouts, selectable per placement in Layout Builder:
 * - grouped: one table per claimer, claimer as the heading.
 * - flat:    one table for all claims, claimer as an optional column.
 *
 * Plugin ID is unchanged from the annotation-based version, so existing
 * placements survive.
 */
#[Block(
  id: 'ird_annotation_table',
  admin_label: new TranslatableMarkup('IRD Annotation Table'),
  category: new TranslatableMarkup('STI Portal'),
)]
final class AnnotationTableBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'layout' => 'grouped',
      'show_claimed_by' => FALSE,
      'show_claimed_at' => FALSE,
      'link_claimer' => TRUE,
      'heading_level' => 'h3',
      'collapse_values' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration();

    $form['layout'] = [
      '#type' => 'radios',
      '#title' => $this->t('Layout'),
      '#options' => [
        'grouped' => $this->t('One table per claimer, with the claimer as a heading'),
        'flat' => $this->t('A single table for all claims'),
      ],
      '#default_value' => $config['layout'],
    ];

    $form['collapse_values'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Combine repeated properties into one row'),
      '#description' => $this->t('Comma-separates the values when the same claimer asserts a property more than once. Untick to show one row per claim paragraph.'),
      '#default_value' => $config['collapse_values'],
    ];

    $form['show_claimed_by'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a "Claimed by" column'),
      '#description' => $this->t('Redundant in the grouped layout, where the claimer is already the heading.'),
      '#default_value' => $config['show_claimed_by'],
      '#states' => [
        'visible' => [
          ':input[name$="[layout]"]' => ['value' => 'flat'],
        ],
      ],
    ];

    $form['show_claimed_at'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show a "Claimed" date column'),
      '#description' => $this->t('Suppressed when repeated properties are combined, since the collapsed values may carry different dates.'),
      '#default_value' => $config['show_claimed_at'],
    ];

    $form['heading_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Claimer heading level'),
      '#description' => $this->t('The solution page uses h3 for its own section headings.'),
      '#options' => ['h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5'],
      '#default_value' => $config['heading_level'],
      '#states' => [
        'visible' => [
          ':input[name$="[layout]"]' => ['value' => 'grouped'],
        ],
      ],
    ];

    $form['link_claimer'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link the claimer to its node'),
      '#default_value' => $config['link_claimer'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['layout'] = (string) $form_state->getValue('layout');
    $this->configuration['heading_level'] = (string) $form_state->getValue('heading_level');
    foreach (['show_claimed_by', 'show_claimed_at', 'link_claimer', 'collapse_values'] as $key) {
      $this->configuration[$key] = (bool) $form_state->getValue($key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof EntityInterface || $node->bundle() !== 'solution') {
      return [];
    }
    if (!$node->hasField('field_claims') || $node->get('field_claims')->isEmpty()) {
      return [];
    }

    $config = $this->getConfiguration();
    $flat = $config['layout'] === 'flat';
    $collapse = (bool) $config['collapse_values'];

    // A date column cannot be reconciled with collapsed values, because the
    // combined values may have been claimed at different times.
    $show_date = (bool) $config['show_claimed_at'] && !$collapse;
    $show_claimer_column = $flat && (bool) $config['show_claimed_by'];

    $groups = $this->collectClaims($node, $collapse);
    if ($groups === []) {
      return [];
    }

    $columns = 2
      + ($show_claimer_column ? 1 : 0)
      + ($show_date ? 1 : 0);

    return $flat
      ? $this->buildFlat($groups, $show_claimer_column, $show_date, $columns, (bool) $config['link_claimer'])
      : $this->buildGrouped($groups, $show_date, $columns, (string) $config['heading_level'], (bool) $config['link_claimer']);
  }

  /**
   * Collects claims, grouped by claimer and then by asserted property.
   */
  private function collectClaims(EntityInterface $node, bool $collapse): array {
    $groups = [];
    $sequence = 0;

    foreach ($node->get('field_claims')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }

      $claimer = $paragraph->hasField('field_claimed_by')
        ? $paragraph->get('field_claimed_by')->entity
        : NULL;

      // Group on entity ID, so two collections sharing a label do not merge.
      $claimer_key = $claimer ? 'node:' . $claimer->id() : 'unknown';

      $property = $paragraph->hasField('field_claim_property')
        ? $paragraph->get('field_claim_property')->entity
        : NULL;

      // When collapsing, rows are keyed by property so repeats merge. When
      // not, every paragraph gets its own row via a unique key.
      $property_key = $collapse
        ? ($property ? 'term:' . $property->id() : 'unspecified')
        : 'claim:' . $sequence++;

      [$value, $signature] = $this->resolveClaimValue($paragraph);
      if ($value === NULL) {
        continue;
      }

      $groups[$claimer_key]['claimer'] = $claimer;

      if (!isset($groups[$claimer_key]['properties'][$property_key])) {
        $timestamp = NULL;
        if ($paragraph->hasField('field_claimed_at') && !$paragraph->get('field_claimed_at')->isEmpty()) {
          $timestamp = (int) $paragraph->get('field_claimed_at')->value;
        }

        $groups[$claimer_key]['properties'][$property_key] = [
          'label' => $property
            ? $this->entityLink($property)
            : ['#plain_text' => (string) $this->t('Unspecified property')],
          'values' => [],
          'signatures' => [],
          'timestamp' => $timestamp,
        ];
      }

      // Skip an identical value asserted twice by the same claimer.
      if (in_array($signature, $groups[$claimer_key]['properties'][$property_key]['signatures'], TRUE)) {
        continue;
      }

      $groups[$claimer_key]['properties'][$property_key]['signatures'][] = $signature;
      $groups[$claimer_key]['properties'][$property_key]['values'][] = $value;
    }

    return $groups;
  }

  /**
   * One table per claimer, claimer rendered as a heading.
   */
  private function buildGrouped(array $groups, bool $show_date, int $columns, string $tag, bool $link): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['project-list-item', 'ird-annotation-tables']],
    ];

    $index = 0;
    foreach ($groups as $group) {
      $build[$index]['heading'] = $this->buildHeading($group['claimer'], $tag, $link);

      $rows = [];
      foreach ($group['properties'] as $property) {
        $rows[] = $this->buildRow($property, NULL, $show_date);
      }

      $build[$index]['table'] = $this->buildTable($rows, $columns, FALSE, $show_date);
      $index++;
    }

    return $build;
  }

  /**
   * A single table for every claim, claimer optionally rendered as a column.
   */
  private function buildFlat(array $groups, bool $show_claimer_column, bool $show_date, int $columns, bool $link): array {
    $rows = [];

    foreach ($groups as $group) {
      $claimer_cell = NULL;
      if ($show_claimer_column) {
        if ($group['claimer']) {
          $claimer_cell = $link
            ? $this->entityLink($group['claimer'])
            : ['#plain_text' => $group['claimer']->label()];
        }
        else {
          $claimer_cell = ['#markup' => ''];
        }
      }

      foreach ($group['properties'] as $property) {
        $rows[] = $this->buildRow($property, $claimer_cell, $show_date);
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['project-list-item', 'ird-annotation-tables']],
      'table' => $this->buildTable($rows, $columns, $show_claimer_column, $show_date),
    ];
  }

  /**
   * Wraps rows in the FAO bordered table.
   *
   * A thead is only emitted once there are more than two columns; with just
   * property and value the row-scoped th carries enough meaning on its own,
   * which is the pattern the rest of the page uses.
   */
  private function buildTable(array $rows, int $columns, bool $show_claimer_column, bool $show_date): array {
    $table = [
      '#theme' => 'table',
      '#rows' => $rows,
      // Keeps data-striping and the odd/even JS classes off the markup; the
      // FAO look is bordered, not striped.
      '#no_striping' => TRUE,
      '#attributes' => [
        'class' => ['table', 'table-bordered', 'ird-annotation-table'],
      ],
    ];

    if ($columns > 2) {
      $header = [$this->t('Property'), $this->t('Value')];
      if ($show_claimer_column) {
        $header[] = $this->t('Claimed by');
      }
      if ($show_date) {
        $header[] = $this->t('Claimed');
      }
      $table['#header'] = $header;
    }

    return $table;
  }

  /**
   * Builds one row: property as row header, then the comma-separated values.
   */
  private function buildRow(array $property, ?array $claimer_cell, bool $show_date): array {
    $cells = [
      [
        'data' => $property['label'],
        'header' => TRUE,
        'scope' => 'row',
        'class' => ['bg-gray-light'],
      ],
      ['data' => $this->joinValues($property['values'])],
    ];

    if ($claimer_cell !== NULL) {
      $cells[] = ['data' => $claimer_cell, 'class' => ['text-muted']];
    }

    if ($show_date) {
      $cells[] = [
        'data' => $property['timestamp']
          ? ['#plain_text' => date('d/m/Y', $property['timestamp'])]
          : ['#markup' => ''],
        'class' => ['text-muted'],
      ];
    }

    return $cells;
  }

  /**
   * Renders several values into one cell, comma-separated.
   */
  private function joinValues(array $values): array {
    if (count($values) === 1) {
      return reset($values);
    }

    $cell = [
      '#type' => 'container',
      '#attributes' => ['class' => ['claim-values']],
    ];

    foreach (array_values($values) as $i => $value) {
      if ($i > 0) {
        $cell['separator_' . $i] = ['#markup' => ', '];
      }
      $cell['value_' . $i] = $value;
    }

    return $cell;
  }

  /**
   * Builds the claimer heading above each table.
   */
  private function buildHeading(?EntityInterface $claimer, string $tag, bool $link): array {
    if (!$claimer) {
      return [
        '#type' => 'html_tag',
        '#tag' => $tag,
        '#value' => (string) $this->t('Unknown collection'),
      ];
    }

    if (!$link) {
      return [
        '#type' => 'html_tag',
        '#tag' => $tag,
        '#value' => $claimer->label(),
      ];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => $tag,
      'link' => $this->entityLink($claimer),
    ];
  }

  /**
   * Resolves a claim's value, preferring a referenced term over a literal.
   *
   * Returns the render array (NULL when the claim has no value) plus a
   * signature used to deduplicate repeated values.
   */
  private function resolveClaimValue(ParagraphInterface $paragraph): array {
    if ($paragraph->hasField('field_claim_ref_value') && !$paragraph->get('field_claim_ref_value')->isEmpty()) {
      $term = $paragraph->get('field_claim_ref_value')->entity;
      if ($term) {
        return [$this->entityLink($term), 'term:' . $term->id()];
      }
    }

    if ($paragraph->hasField('field_claim_literal_value') && !$paragraph->get('field_claim_literal_value')->isEmpty()) {
      $text = (string) $paragraph->get('field_claim_literal_value')->value;

      // field_claim_literal_value is string_long, so harvested values can be
      // multi-line. Single-line values go through #plain_text to avoid the
      // <p> wrapper that processed_text adds inside a table cell.
      $value = str_contains($text, "\n")
        ? [
          '#type' => 'processed_text',
          '#text' => $text,
          '#format' => 'plain_text',
        ]
        : ['#plain_text' => $text];

      return [$value, 'literal:' . md5($text)];
    }

    return [NULL, ''];
  }

  /**
   * Renders an entity as a link to its canonical page.
   */
  private function entityLink(EntityInterface $entity): array {
    try {
      return [
        '#type' => 'link',
        '#title' => $entity->label(),
        '#url' => $entity->toUrl(),
      ];
    }
    catch (\Exception) {
      return ['#plain_text' => $entity->label()];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = parent::getCacheTags();
    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof EntityInterface) {
      return $tags;
    }

    $tags = Cache::mergeTags($tags, $node->getCacheTags());

    // Claimer, property and value labels are rendered here, so their tags are
    // collected too; a renamed term would otherwise leave a stale row.
    if ($node->hasField('field_claims')) {
      foreach ($node->get('field_claims')->referencedEntities() as $paragraph) {
        $tags = Cache::mergeTags($tags, $paragraph->getCacheTags());
        foreach (['field_claimed_by', 'field_claim_property', 'field_claim_ref_value'] as $field) {
          if ($paragraph->hasField($field) && ($referenced = $paragraph->get($field)->entity)) {
            $tags = Cache::mergeTags($tags, $referenced->getCacheTags());
          }
        }
      }
    }

    return $tags;
  }

}