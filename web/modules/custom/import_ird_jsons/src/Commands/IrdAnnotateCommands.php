<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;
use Drush\Commands\DrushCommands;

/**
 * Imports per-collection field claims from a normalized IRD JSON.
 *
 * Each value a source asserts about a solution becomes one `claims` paragraph
 * attached to that solution's `field_claims`. A claim is a reified statement:
 *
 *   solution  sti:hasFieldClaim  claim
 *     claim     sti:claimProperty  <property-uri>    (the predicate)
 *     claim     sti:claimLiteralValue "…"
 *          or sti:claimRefValue    <value-uri>       (the object)
 *     claim     sti:claimedBy      <collection-uri>  (provenance)
 *     claim     sti:claimedAt      "…"^^xsd:dateTime
 *
 * Predicates and categorical values live in two shared hierarchical vocabularies
 * (parent term = collection id):
 *
 *   claim_properties:  {collection} → {key}
 *   claim_values:      {collection} → {key} → {value}
 *
 * Term URIs are written to field_property_uri so rdf_sync emits them as RDF
 * resources rather than entity-id-based URIs.
 *
 * Classification (categorical vs literal) and custom_fields flattening are
 * handled upstream by the IRD Python library. JSON shape expected:
 *
 *   {
 *     "meta": {
 *       "collection_id": "stf",
 *       "field_config": {
 *         "organization_type": { "categorical": true },
 *         "technologies":      { "categorical": true, "separator": "|" },
 *         "readiness_level":   { "categorical": false }
 *       }
 *     },
 *     "records": [
 *       {
 *         "core_fields": { "id": "stf_001", "title": "…" },
 *         "claims": {
 *           "organization_type": { "value": "Non-profit" },
 *           "technologies":      { "value": ["GIS", "Remote sensing"] },
 *           "readiness_level":   { "value": "TRL 7" }
 *         }
 *       }
 *     ]
 *   }
 *
 * Idempotent: re-running clears only this collection's claims per solution;
 * claims from other collections are left untouched.
 *
 * Rollback is handled by the migrate plugin framework (drush migrate:rollback).
 * Taxonomy term cleanup after rollback requires manual deletion or a dedicated
 * term-cleanup command.
 *
 * Usage:
 *   drush ird:annotate stf path/to/stf.json
 */
final class IrdAnnotateCommands extends DrushCommands {

  private const VOCAB_PROPERTIES  = 'claim_properties';
  private const VOCAB_VALUES      = 'claim_values';
  private const PARAGRAPH_BUNDLE  = 'claims';
  private const SOLUTION_BUNDLE   = 'solution';
  private const COLLECTION_BUNDLE = 'collection';

  /**
   * Field on vocabulary terms holding the canonical RDF URI.
   */
  private const FIELD_TERM_URI = 'field_property_uri';

  /** Base namespace for minted predicate and value URIs. */
  private const URI_BASE = 'http://data.fao.org/ird/';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
  ) {
    parent::__construct();
  }

  /**
   * Import field claims for a collection from a normalized JSON.
   *
   * @command ird:annotate
   *
   * @param string $collection_id
   *   Machine-name slug for the collection (e.g. "stf").
   * @param string $json_path
   *   Path to the normalized IRD JSON.
   */
  public function annotate(string $collection_id, string $json_path): void {
    $data = json_decode(file_get_contents($json_path), TRUE, 512, JSON_THROW_ON_ERROR);

    $collection_nid = $this->loadCollectionNid($collection_id);
    print("COLLECTION NID: " . $collection_nid);
    $field_config   = $data['meta']['field_config'] ?? [];

    $properties_parent = $this->ensureTerm(self::VOCAB_PROPERTIES, $collection_id);
    $values_parent     = $this->ensureTerm(self::VOCAB_VALUES, $collection_id);

    $created = $skipped = 0;

    foreach ($data['records'] as $record) {
      $id       = $record['core_fields']['id'] ?? NULL;
      $solution = $id ? $this->loadSolution($id) : NULL;
      if (!$solution) {
        $this->logger()->warning("No solution for id={$id} — skipping.");
        $skipped++;
        continue;
      }

      $this->clearCollectionClaims($solution, $collection_nid);

      foreach ($record['claims'] as $key => $claim) {
        $meta           = $field_config[$key] ?? [];
        $is_categorical = (bool) ($meta['categorical'] ?? FALSE);
        $separator      = $meta['separator'] ?? NULL;
        $property_term  = $this->ensureKeyTerm(self::VOCAB_PROPERTIES, $collection_id, $key, $properties_parent);

        foreach ($this->normalizeValues($claim['value'], $separator) as $single) {
          $paragraph = $is_categorical
            ? $this->buildRefClaim($collection_id, $key, $single, $property_term, $values_parent, $collection_nid)
            : $this->buildLiteralClaim($single, $property_term, $collection_nid);

          $solution->get('field_claims')->appendItem([
            'target_id'          => $paragraph->id(),
            'target_revision_id' => $paragraph->getRevisionId(),
          ]);
          $created++;
        }
      }

      $solution->save();


    }
    $this->syncClaimFieldsToIndex();

    // 2. Derive view ID and ensure the collection view exists.
    $slug         = $this->slug($collection_id);
    $view_id      = 'collection_' . $slug . '_solutions';
    $facet_source = 'search_api:views_page__' . $view_id . '__page_1';
    $this->ensureCollectionView($collection_id, $view_id);

    // 3. Create facets.facet.* for categorical fields.
    // Field schema is already saved, so getField() checks will pass.
    $this->syncFacetsForCollection($collection_id, $collection_nid, $facet_source);

    // 4. Connect the facets to the view created at 2.
    $this->attachFacetsToView($collection_id, $view_id);

    $this->logger()->success("$created claims created, $skipped records skipped.");
  }

  // ---------------------------------------------------------------------------
  // Claim construction
  // ---------------------------------------------------------------------------

  /** Build a literal-valued claim paragraph (saved, ready to attach). */
  private function buildLiteralClaim(string $value, TermInterface $property, int $collection_nid): ParagraphInterface {
    return $this->createClaim([
      'field_claim_property'      => ['target_id' => $property->id()],
      'field_claim_literal_value' => $value,
    ], $collection_nid);
  }

  /**
   * Build a reference-valued claim paragraph (saved, ready to attach).
   */
  private function buildRefClaim(
    string $collection_id,
    string $key,
    string $value,
    TermInterface $property,
    TermInterface $values_parent,
    int $collection_nid,
  ): ParagraphInterface {
    $key_term   = $this->ensureKeyTerm(self::VOCAB_VALUES, $collection_id, $key, $values_parent);
    $value_term = $this->ensureTerm(
      self::VOCAB_VALUES, $value, (int) $key_term->id(), $this->valueUri($collection_id, $key, $value),
    );

    return $this->createClaim([
      'field_claim_property'  => ['target_id' => $property->id()],
      'field_claim_ref_value' => ['target_id' => $value_term->id()],
    ], $collection_nid);
  }

  /** Create and save a claims paragraph with shared provenance fields. */
  private function createClaim(array $fields, int $collection_nid): ParagraphInterface {
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $this->paragraphStorage()->create([
      'type'             => self::PARAGRAPH_BUNDLE,
      'field_claimed_by' => ['target_id' => $collection_nid],
      'field_claimed_at' => $this->time->getRequestTime(),
    ] + $fields);
    $paragraph->save();
    return $paragraph;
  }

  /**
   * Delete a solution's claims from one collection (caller saves the solution).
   */
  private function clearCollectionClaims(NodeInterface $solution, int $collection_nid): void {
    $kept = [];
    foreach ($solution->get('field_claims') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }
      if ((int) $paragraph->get('field_claimed_by')->target_id === $collection_nid) {
        $paragraph->delete();
        continue;
      }
      $kept[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
    }
    $solution->set('field_claims', $kept);
  }

  // ---------------------------------------------------------------------------
  // View construction
  // Create a view for the new collection, listing all categorical claims as facets
  // ---------------------------------------------------------------------------
  private function ensureCollectionView(string $collection_id, string $view_id): void {
      $config_name = 'views.view.' . $view_id;
      $cf = \Drupal::configFactory();

      if (!$cf->getEditable($config_name)->isNew()) {
          $this->logger()->notice("View '$view_id' already exists — skipping.");
          return;
      }

      $template = $cf->get('views.view.test_search')->getRawData();
      if (!$template) {
          $this->logger()->error("Template view 'test_search' not found — cannot create '$view_id'.");
          return;
      }

      $template['id']    = $view_id;
      $template['label'] = 'Solutions: ' . $collection_id;
      $template['uuid']  = \Drupal::service('uuid')->generate();

      // Remove the test path — the view is embedded in the collection page,
      // not routed independently. If you do need a path, set it here.
      unset($template['display']['page_1']['display_options']['path']);

      $cf->getEditable($config_name)->setData($template)->save();
      $this->logger()->notice("View '$view_id' created from test_search template.");
  }
  private function attachFacetsToView(string $collection_id, string $view_id): void {
      $config_name = 'views.view.' . $view_id;
      $cf = \Drupal::configFactory();

      if ($cf->getEditable($config_name)->isNew()) {
          $this->logger()->warning("View '$view_id' does not exist — stopping.");
          return;
      }

      $view = $cf->get($config_name)->getRawData();
      if (!$view) {
          $this->logger()->error("View '$config_name' not found — cannot append any facets to it.");
          return;
      }

      
      $facet_source_id = 'search_api:views_page__' . $view_id . '__page_1';
      $facet_storage = $this->entityTypeManager->getStorage('facets_facet');
      $facet_ids = $facet_storage->getQuery()
                  ->condition('facet_source_id', $facet_source_id)
                  ->accessCheck(FALSE)
                  ->execute();


      $added = 0;
      foreach ($facet_storage->loadMultiple($facet_ids) as $facet) {
        $filter_id = 'facets_' . $facet->id();
        $view['display']['default']['display_options']['filters'][$filter_id] = [
            'id'           => $filter_id,
            'table'        => 'search_api_index_published',
            'field'        => $filter_id,
            'relationship' => 'none',
            'group_type'   => 'group',
            'admin_label'  => '',
            'plugin_id'    => 'facets_filter',
            'operator'     => '=',
            'value'        => '',
            'group'        => 1,
            'exposed'      => TRUE,
            'expose'       => [
                'operator_id'              => '',
                'label'                    => $facet->label(),
                'description'              => '',
                'use_operator'             => FALSE,
                'operator'                 => '',
                'operator_limit_selection' => FALSE,
                'operator_list'            => [],
                'identifier'               => $facet->id(),
                'required'                 => FALSE,
                'remember'                 => FALSE,
                'multiple'                 => TRUE,
                'remember_roles'           => ['authenticated' => 'authenticated'],
            ],
            'is_grouped'    => FALSE,
            'group_info'    => [
                'label'                  => '',
                'description'            => '',
                'identifier'             => '',
                'optional'               => TRUE,
                'widget'                 => 'select',
                'multiple'               => FALSE,
                'remember'               => FALSE,
                'default_group'          => 'All',
                'default_group_multiple' => [],
                'group_items'            => [],
            ],
            'hierarchy'     => FALSE,
            'label_display' => 'visible',
            'facet'         => [
                'query_operator'    => 'or',
                'hard_limit'        => 0,
                'min_count'         => 1,
                'show_numbers'      => FALSE,
                'processor_configs' => [
                    'count_limit' => [
                        'processor_id' => 'count_limit',
                        'weights'      => ['build' => 50],
                        'settings'     => [
                            'minimum_items' => 1,
                            'maximum_items' => NULL,
                        ],
                    ],
                ],
            ],
        ];
        $added++;
      }
      $view['display']['page_1']['display_options']['filter_groups'] ??= [
        'operator' => 'AND',
        'groups'   => [1 => 'AND'],
      ];
      
      $cf->getEditable($config_name)->setData($view)->save();
      $this->logger()->notice("Added " . $added . " facets to " . $view_id);
  }

  private function syncFacetsForCollection(
    string $collection_id,
    int $collection_nid,
    string $facet_source_id,
  ): void {
      // Find all categorical claims made by this collection.
      $pids = $this->paragraphStorage()->getQuery()
          ->condition('type',               self::PARAGRAPH_BUNDLE)
          ->condition('field_claimed_by',   $collection_nid) //! this lookup might fail?
          ->condition('field_claim_ref_value', NULL, 'IS NOT NULL')
          ->accessCheck(FALSE)
          ->execute();

      if (empty($pids)) {
          $this->logger()->warning("No categorical claims for '$collection_id' — no facets created.");
          return;
      }

      // Collect unique property terms used by these claims.
      $property_tids = [];
      foreach ($this->paragraphStorage()->loadMultiple($pids) as $paragraph) {
          if ($tid = $paragraph->get('field_claim_property')->target_id) {
              $property_tids[$tid] = $tid;
          }
      }

      $index         = $this->entityTypeManager->getStorage('search_api_index')->load('published');
      $facet_storage = $this->entityTypeManager->getStorage('facets_facet');
      $cf            = \Drupal::configFactory();
      $theme         = \Drupal::config('system.theme')->get('default');
      $slug          = $this->slug($collection_id);

      // $region = 'sidebar_second';
      // foreach ($cf->listAll('block.block.') as $cfg_name) {
      //     $data = $cf->get($cfg_name)->getRawData();
      //     if (str_starts_with($data['plugin'] ?? '', 'facet_block:')
      //         && ($data['theme'] ?? '') === $theme
      //         && !empty($data['region'])
      //     ) {
      //         $region = $data['region'];
      //         break;
      //     }
      // }

      $weight = 0;
      foreach ($this->termStorage()->loadMultiple($property_tids) as $term) {
          $key_slug = $this->slug($term->getName());
          $field_id = 'claim_' . $key_slug;

          if (!$index->getField($field_id)) {
              $this->logger()->warning("Field '$field_id' not in index — skipping facet.");
              continue;
          }

          // Facet entity: shared across collections that happen to use the same property.
          if (!$facet_storage->load($field_id)) {
              $facet_storage->create([
                  'id'               => $field_id,
                  'name'             => $term->getName(),
                  'url_alias'        => $field_id,
                  'weight'           => $weight,
                  'widget'           => ['type' => 'dropdown', 'config' => ['show_numbers' => TRUE]],
                  'facet_source_id'  => $facet_source_id,
                  'field_identifier' => $field_id,
                  'only_visible_when_facet_source_is_visible' => TRUE,
                  'show_only_one_result' => FALSE,
                  'empty_behavior'   => ['behavior' => 'none'],
                  'facet_settings'   => ['url_processor' => 'query_string'],
                  'processor_configs' => [],
              ])->save();
              $this->logger()->notice("Facet created: $field_id");
          }

          // Block: collection-specific so placement is independent per collection.
          // $block_id   = 'ird_' . substr($slug . '_' . $key_slug, 0, 55);
          // $block_name = 'block.block.' . $block_id;

          // if ($cf->getEditable($block_name)->isNew()) {
          //     $cf->getEditable($block_name)->setData([
          //         'langcode'     => 'en',
          //         'status'       => TRUE,
          //         'id'           => $block_id,
          //         'theme'        => $theme,
          //         'region'       => $region,
          //         'weight'       => $weight,
          //         'provider'     => 'facets',
          //         'plugin'       => 'facet_block:' . $field_id,
          //         'settings'     => [
          //             'id'            => 'facet_block:' . $field_id,
          //             'label'         => $term->getName(),
          //             'label_display' => 'visible',
          //             'provider'      => 'facets',
          //         ],
          //         'visibility'   => [],
          //         'dependencies' => [
          //             'config' => ['facets.facet.' . $field_id],
          //             'module' => ['facets'],
          //         ],
          //     ])->save();
          //     $this->logger()->notice("Block placed: $block_id → $region");
          // }

          $weight++;
      }
  }

  // ---------------------------------------------------------------------------
  // Taxonomy helpers (idempotent)
  // ---------------------------------------------------------------------------

  private function ensureKeyTerm(string $vid, string $collection_id, string $key, TermInterface $parent): TermInterface {
    $uri = $vid === self::VOCAB_PROPERTIES ? $this->propertyUri($collection_id, $key) : NULL;
    return $this->ensureTerm($vid, $key, (int) $parent->id(), $uri);
  }

  /** Find-or-create a term by vocabulary, name, and parent; set its URI if given. */
  private function ensureTerm(string $vid, string $name, ?int $parent_tid = NULL, ?string $uri = NULL): TermInterface {
    if ($existing = $this->findTerm($vid, $name, $parent_tid)) {
      return $existing;
    }
    $term = $this->termStorage()->create([
      'vid'    => $vid,
      'name'   => $name,
      'parent' => $parent_tid !== NULL ? [$parent_tid] : [],
    ]);
    if ($uri !== NULL && $term->hasField(self::FIELD_TERM_URI)) {
      $term->set(self::FIELD_TERM_URI, $uri);
    }
    $term->save();
    return $term;
  }

  private function findTerm(string $vid, string $name, ?int $parent_tid = NULL): ?TermInterface {
    $properties = ['vid' => $vid, 'name' => $name];
    if ($parent_tid !== NULL) {
      $properties['parent'] = $parent_tid;
    }
    $matches = $this->termStorage()->loadByProperties($properties);
    return $matches ? reset($matches) : NULL;
  }

  // ---------------------------------------------------------------------------
  // Source data helpers
  // ---------------------------------------------------------------------------

  /**
   * Normalize a claim value into a flat list of scalar strings.
   *
   * @return string[]
   */
  private function normalizeValues(mixed $value, ?string $separator = NULL): array {
    if (is_array($value)) {
      $items = $value;
    }
    elseif ($separator !== NULL) {
      $items = array_map('trim', explode($separator, (string) $value));
    }
    else {
      $items = [(string) $value];
    }
    $flat = [];
    foreach ($items as $item) {
      $flat[] = is_array($item) ? (string) ($item['value'] ?? reset($item)) : (string) $item;
    }
    return array_values(array_filter($flat, static fn(string $v): bool => $v !== ''));
  }

  private function loadSolution(string $original_id): ?NodeInterface {
    $matches = $this->nodeStorage()->loadByProperties([
      'type'                       => self::SOLUTION_BUNDLE,
      'field_original_internal_id' => $original_id,
    ]);
    return $matches ? reset($matches) : NULL;
  }

  private function loadCollectionNid(string $collection_id): int {
    $matches = $this->nodeStorage()->loadByProperties([
      'type'     => self::COLLECTION_BUNDLE,
      'short_id' => $collection_id,
    ]);
    if (!$matches) {
      throw new \InvalidArgumentException(
        "No collection found with short_id={$collection_id}."
      );
    }
    return (int) reset($matches)->id();
  }

  private function syncClaimFieldsToIndex(): void {
    $index = $this->entityTypeManager
      ->getStorage('search_api_index')->load('published');

    $processor = $index->getProcessor('claim_fields');
    if (!$processor) {
      $this->logger()->warning('claim_fields processor not enabled — skipping field sync.');
      return;
    }

    $added = 0;
    foreach ($processor->getPropertyDefinitions(NULL) as $key => $definition) {
      if ($index->getField($key)) {
        continue;
      }
      $field = \Drupal::service('search_api.fields_helper')
        ->createField($index, $key, [
          'type'          => 'string',
          'label'         => $definition->getLabel(),
          'property_path' => $key,
          'datasource_id' => NULL,
        ]);
      $index->addField($field);
      $added++;
    }

    if ($added > 0) {
      $index->save();
      $this->logger()->success("Synced $added new claim field(s) to the search index.");
    }
  }

  // ---------------------------------------------------------------------------
  // URI minting
  // ---------------------------------------------------------------------------

  /** Predicate URI for a key: http://data.fao.org/ird/{collection}#{key} */
  private function propertyUri(string $collection_id, string $key): string {
    return self::URI_BASE . $this->slug($collection_id) . '#' . $this->slug($key);
  }

  /** Value URI for a categorical value: http://data.fao.org/ird/{collection}/{key}#{value} */
  private function valueUri(string $collection_id, string $key, string $value): string {
    return self::URI_BASE . $this->slug($collection_id) . '/' . $this->slug($key) . '#' . $this->slug($value);
  }

  private function slug(string $value): string {
    return trim(preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower($value)), '_');
  }

  // ---------------------------------------------------------------------------
  // Storage accessors
  // ---------------------------------------------------------------------------

  private function nodeStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

  private function termStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('taxonomy_term');
  }

  private function paragraphStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('paragraph');
  }


}