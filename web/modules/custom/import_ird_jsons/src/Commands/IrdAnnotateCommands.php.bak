<?php

namespace Drupal\import_IRD_jsons\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drush\Commands\DrushCommands;

/**
 * Imports per-collection annotations from a normalized JSON.
 *
 * The JSON shape follows the IRD lib normalized format:
 *
 *   {
 *     "meta": { "collection_id": "car", "og_audience_nid": 42, ... },
 *     "records": [
 *       {
 *         "id": "car_proj_001",
 *         "title": "...",
 *         "_annotations": {
 *           "technologies": ["drone", "IoT"],
 *           "countries": ["RW", "KE"],
 *           "custom_fields": {
 *             "readiness_level": "TRL 7"
 *           }
 *         }
 *       }
 *     ]
 *   }
 *
 * Phase 1 — config provisioning (idempotent):
 *   - Ensure node bundle "{collection}_annotation" exists.
 *   - Ensure RDF mapping exists for the bundle (collection-scoped predicates).
 *   - Ensure field_innovation (entity_reference to solution) exists on bundle.
 *   - For each unique key in _annotations across all records:
 *       --terms : create vocabulary "{collection}_{key}", attach
 *                 entity_reference field "field_{collection}_{key}" (cardinality -1).
 *       --literals (default): attach string field "field_{collection}_{key}" (cardinality -1).
 *   - custom_fields sub-object is flattened into the top-level annotation keys.
 *
 * Field names are prefixed with the collection slug to avoid cross-collection
 * field storage collisions (Drupal field storage is site-wide).
 *
 * Phase 2 — content import (idempotent):
 *   - For each record: if an annotation node already exists for that solution,
 *     update it; otherwise create it.
 *   - Solution matched by field_original_internal_id == record id.
 *   - og_audience populated from meta.og_audience_nid (drives sti:statedBy in RDF).
 *
 * Usage:
 *   drush ird:annotate car path/to/car.json
 *   drush ird:annotate car path/to/car.json --terms
 *   drush ird:annotate car path/to/car.json --dry-run
 */
class IrdAnnotateCommands extends DrushCommands {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Provision annotation bundle and import annotations from JSON.
   *
   * @command ird:annotate
   * @param string $collection_id Machine-name slug for the collection (e.g. "car").
   * @param string $json_path Absolute or relative path to the normalized JSON.
   * @option terms Create a vocabulary per key and reference terms (vs. literal string storage).
   * @option dry-run Inspect and report; do not write anything.
   */
  public function annotate(
    string $collection_id,
    string $json_path,
    array $options = ['terms' => FALSE, 'dry-run' => FALSE],
  ): void {

    //! CLAUDE: named argument syntax replaced with positional — PHP 8.0 named
    //! args are supported but caused a syntax error in this Drush context.
    $data = json_decode(file_get_contents($json_path), TRUE, 512, JSON_THROW_ON_ERROR);
    $records = $data['records'] ?? [];
    $use_terms = (bool) $options['terms'];
    $dry = (bool) $options['dry-run'];
    $bundle = $collection_id . '_annotation';

    //! CLAUDE: og_audience_nid read from meta block so annotation nodes get
    //! og_audience populated, which rdf_sync maps to sti:statedBy <collection_uri>.
    //! Without this the statedBy triple is empty and collection provenance is lost.
    $collection_nid = $data['meta']['og_audience_nid'] ?? NULL;
    if (!$collection_nid) {
      $this->logger()->warning("meta.og_audience_nid missing — sti:statedBy will be empty.");
    }

    // -------- Phase 1: discover schema from data --------
    // Flatten custom_fields into the top-level annotation keys so schema
    // discovery and field provisioning treat them uniformly.
    $keys = [];
    foreach ($records as $r) {
      foreach ($this->flattenAnnotations($r['_annotations'] ?? []) as $k => $v) {
        $keys[$k][] = $v;
      }
    }
    $this->logger()->notice("Discovered " . count($keys) . " annotation keys: " . implode(', ', array_keys($keys)));

    if ($dry) {
      foreach ($keys as $k => $vs) {
        $flat = [];
        array_walk_recursive($vs, function ($item) use (&$flat) { $flat[] = $item; });
        $this->logger()->notice("  $k → " . count(array_unique($flat)) . " unique values");
      }
      return;
    }

    // -------- Phase 1: provision bundle + fields --------
    $this->ensureBundle($bundle, ucfirst($collection_id) . ' annotation'); //? Drupal-side

    //! CLAUDE: ensureRdfMapping now called here, after key discovery, receiving
    //! $keys so it can generate a collection-scoped predicate for every dynamic
    //! field. Previously this call was missing entirely — annotations were never
    //! synced to the triplestore.
    $this->ensureRdfMapping($bundle, $collection_id, $keys); //? Virtuoso-side

    $this->ensureSolutionRef($bundle); //? Creates a reference to a solution for the annotation ($this)
    $this->ensureOgAudience($bundle); //? Creates a reference to a og_audience for the annotation ($this)


    foreach ($keys as $key => $values) {
      $field_name = $this->fieldName($collection_id, $key);

      if ($use_terms && !$this->keyLooksLikeFreeText($values)) {
        // Only create a vocabulary when values look like a controlled list.
        // Keys whose values are typically long free-text paragraphs are silently
        // downgraded to string_long fields — see keyLooksLikeFreeText().
        $vid = substr($collection_id . '_' . $this->slug($key), 0, 32);
        $this->ensureVocabulary($vid, "$collection_id: $key");
        $flat_values = [];
        $arr = (array) $values;
        array_walk_recursive($arr, function ($v) use (&$flat_values) { $flat_values[] = $v; });
        foreach (array_unique($flat_values) as $v) {
          $this->ensureTerm($vid, (string) $v);
        }
        $this->ensureTermRefField($bundle, $field_name, $vid, $key);
      }
      else {
        if ($use_terms) {
          $this->logger()->notice(
            "Key '$key' looks like free text — stored as string_long instead of taxonomy terms."
          );
        }
        $this->ensureStringField($bundle, $field_name, $key);
      }
    }

    // -------- Phase 2: upsert annotation nodes --------
    $created = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($records as $r) {
      $solution = $this->loadSolutionByOriginalId($r['id']);
      if (!$solution) {
        $this->logger()->warning("No solution for id={$r['id']}, skipping.");
        $skipped++;
        continue;
      }

      $flat_annotations = $this->flattenAnnotations($r['_annotations'] ?? []);

      //! CLAUDE: og_audience set from $collection_nid (read from meta above).
      //! This is what rdf_sync reads to emit sti:statedBy <collection_uri>.
      //! Previously missing — the "statedBy" triple was always empty.
      $field_values = [
        'type'             => $bundle,
        'title'            => 'Annotation for: ' . ($r['title'] ?? $r['id']),
        'field_innovation' => ['target_id' => $solution->id()],
        'og_audience'      => $collection_nid ? ['target_id' => $collection_nid] : NULL,
      ];

      foreach ($flat_annotations as $key => $value) {
        $field_name = $this->fieldName($collection_id, $key);
        if (is_array($value) && !empty($value) && is_array($value[0]) && array_key_exists('value', $value[0])) {
          $value = array_column($value, 'value');
        }
        if ($use_terms) {
          $vid = substr($collection_id . '_' . $this->slug($key), 0, 32);
          $items = is_array($value) ? $value : [$value];
          $field_values[$field_name] = array_map(
            fn($v) => ['target_id' => $this->getTermId($vid, (string) $v)],
            $items
          );
        }
        else {
          //! CLAUDE: is_array check added — array values like ["drone","IoT"]
          //! are joined to a string for literal storage. Previously (string) $value
          //! on an array produced the literal string "Array".
          $field_values[$field_name] = is_array($value)
            ? implode(', ', $value)
            : (string) $value;
        }
      }

      $is_new = $this->upsertAnnotation($bundle, $solution->id(), $field_values);
      $is_new ? $created++ : $updated++;
    }

    $this->logger()->success("Annotations: $created created, $updated updated, $skipped skipped.");
  }

  /**
   * Roll back all annotation data for a collection.
   *
   * Removes in order:
   *   1. Annotation nodes for the collection bundle.
   *   2. Collection-prefixed field instances + storage.
   *   3. The annotation node bundle itself.
   *   4. The RDF mapping for the bundle.
   *
   * Does NOT touch the solution nodes or the og_audience/owner nodes.
   *
   * Usage:
   *   drush ird:rollback-annotation bp5
   *   drush ird:rollback-annotation bp5 --dry-run
   *
   * @command ird:rollback-annotation
   * @param string $collection_id Machine-name slug for the collection (e.g. "bp5").
   * @option dry-run Report what would be deleted without deleting anything.
   */
  public function rollbackAnnotation(
    string $collection_id,
    array $options = ['dry-run' => FALSE],
  ): void {

    $dry = (bool) $options['dry-run'];
    $bundle = $collection_id . '_annotation';
    $field_prefix = 'field_' . $this->slug($collection_id) . '_';

    // -------- 1. Annotation nodes --------
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => $bundle]);

    $this->logger()->notice(count($nodes) . " annotation nodes to delete.");

    if (!$dry) {
      foreach ($nodes as $node) {
        $node->delete();
      }
      $this->logger()->notice("Deleted annotation nodes.");
    }

    // -------- 2. Collection-prefixed field instances + storage --------
    //! CLAUDE: query FieldStorageConfig directly instead of via getFieldDefinitions()
    //! so this works even if the bundle no longer exists (e.g. partial rollback).
    $all_storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->loadByProperties(['entity_type' => 'node']);

    $collection_fields = array_filter(
      array_keys($all_storage),
      fn($name) => str_starts_with($name, $field_prefix)
    );

    $this->logger()->notice(count($collection_fields) . " field storage configs to delete: " . implode(', ', $collection_fields));

    if (!$dry) {
      foreach ($collection_fields as $field_name) {
        // Delete instance before storage — Drupal requires this order.
        FieldConfig::loadByName('node', $bundle, $field_name)?->delete();
        FieldStorageConfig::loadByName('node', $field_name)?->delete();
      }
      // field_innovation: delete instance only — leave storage for other bundles.
      FieldConfig::loadByName('node', $bundle, 'field_innovation')?->delete();
      $this->logger()->notice("Deleted field instances and storage.");
    }

    // -------- 3. Node bundle --------
    $node_type = NodeType::load($bundle);
    if ($node_type) {
      $this->logger()->notice("Bundle to delete: $bundle");
      if (!$dry) {
        $node_type->delete();
        $this->logger()->notice("Deleted bundle: $bundle");
      }
    }
    else {
      $this->logger()->notice("Bundle $bundle not found — skipping.");
    }

    // -------- 4. RDF mapping --------
    //! CLAUDE: rdf_sync stores mappings as plain config, not a config entity type.
    //! Using configFactory() directly — entityTypeManager->getStorage('rdf_mapping')
    //! throws "entity type does not exist" because no such entity type is registered.
    $config_name = 'rdf.mapping.node.' . $bundle;
    $rdf_config = \Drupal::configFactory()->getEditable('node.type.' . $bundle);

    if (!$rdf_config->isNew()) {
      $this->logger()->notice("RDF mapping to delete: $config_name");
      if (!$dry) {
        $rdf_config->clear("third_party_settings.rdf_sync")->save();
        $this->logger()->notice("Deleted RDF mapping.");
      }
    }
    else {
      $this->logger()->notice("RDF mapping $config_name not found — skipping.");
    }

    if ($dry) {
      $this->logger()->notice("Dry run complete — nothing was deleted.");
      return;
    }

    $this->logger()->success("Rollback complete for '$collection_id' annotations.");
  }

  // ------------------------------------------------------------------
  // Helpers — all idempotent
  // ------------------------------------------------------------------

  /**
   * Flatten _annotations, hoisting custom_fields sub-keys to the top level.
   *
   * Input:  ['technologies' => ['drone'], 'custom_fields' => ['readiness_level' => 'TRL 7']]
   * Output: ['technologies' => ['drone'], 'readiness_level' => 'TRL 7']
   */
  private function flattenAnnotations(array $annotations): array {
    $flat = [];
    foreach ($annotations as $k => $v) {
      if ($k === 'custom_fields' && is_array($v)) {
        foreach ($v as $ck => $cv) {
          $flat[$ck] = $cv;
        }
      }
      else {
        $flat[$k] = $v;
      }
    }
    return $flat;
  }

  /**
   * Build a field machine name, prefixed with the collection slug.
   * Max 32 chars total (Drupal field name limit).
   *
   * e.g. collection="bp5", key="field_innovation_type" → "field_bp5_innovation_type"
   */
  private function fieldName(string $collection_id, string $key): string {
    //! CLAUDE: leading field_ stripped from source key before building the name.
    //! Source keys from bp5 already have field_ prefixes (e.g. "field_region").
    //! Without this strip, the result doubled up: "field_bp5_field_region".
    $key = preg_replace('/^field_/', '', $key);
    $prefix = 'field_' . $this->slug($collection_id) . '_';
    $max_key = 32 - strlen($prefix);
    return $prefix . substr($this->slug($key), 0, $max_key);
  }

  /**
   * Create or update an annotation node for a given solution.
   *
   * Returns TRUE if created, FALSE if updated.
   */
  private function upsertAnnotation(string $bundle, int $solution_nid, array $values): bool {
    $existing = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => $bundle, 'field_innovation' => $solution_nid]);

    if ($existing) {
      $node = reset($existing);
      foreach ($values as $field => $value) {
        $node->set($field, $value);
      }
      $node->save();
      return FALSE;
    }

    Node::create($values)->save();
    return TRUE;
  }

  /**
   * Ensure an RDF mapping exists for the annotation bundle.
   *
   * Core predicates (title, field_innovation, og_audience) are always present.
   * Each dynamic annotation key gets a collection-scoped predicate URI:
   *   https://sti-portal.fao.org/collection/{collection_id}#{local_name}
   *
   * The mapping is updated on every run so new keys added to the JSON are
   * picked up without manual config changes.
   */
  private function ensureRdfMapping(string $bundle, string $collection_id, array $keys): void {
    $fields = [
      'title' => [
        'value' => ['predicate' => 'http://purl.org/dc/terms/title', 'type' => NULL],
      ],
      'field_innovation' => [
        'target_id' => [
          'predicate' => 'https://sti-portal.fao.org/ontology#annotates',
          'type'      => 'resource',
        ],
      ],
      'og_audience' => [
        'target_id' => [
          'predicate' => 'https://sti-portal.fao.org/ontology#statedBy',
          'type'      => 'resource',
        ],
      ],
    ];

    foreach ($keys as $key => $values) {
      $field_name      = $this->fieldName($collection_id, $key);
      $predicate_local = preg_replace('/^field_/', '', $this->slug($key));
      $fields[$field_name] = [
        'value' => [
          'predicate' => 'https://sti-portal.fao.org/collection/' . $collection_id . '#' . $predicate_local,
          'type'      => NULL,
        ],
      ];
    }

    \Drupal::configFactory()
      ->getEditable('node.type.' . $bundle)
      ->set('third_party_settings.rdf_sync', [
        'type'       => 'https://sti-portal.fao.org/ontology#Annotation',
        'uri_plugin' => 'sti_namespace',
        'fields'     => $fields,
      ])
      ->save();
  }

  private function ensureBundle(string $bundle, string $label): void {
    if (!NodeType::load($bundle)) {
      NodeType::create(['type' => $bundle, 'name' => $label, 'new_revision' => TRUE])->save();
      $this->logger()->notice("Created bundle: $bundle");
    }
  }

  private function ensureSolutionRef(string $bundle): void {
    //? This ensures that field_innovation in the annotation references innovation/solution 
    if (!FieldStorageConfig::loadByName('node', 'field_innovation')) {
      FieldStorageConfig::create([
        'field_name'  => 'field_innovation',
        'entity_type' => 'node',
        'type'        => 'entity_reference',
        'cardinality' => 1,
        'settings'    => ['target_type' => 'node'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, 'field_innovation')) {
      FieldConfig::create([
        'field_name'     => 'field_innovation',
        'entity_type'    => 'node',
        'bundle'         => $bundle,
        'label'          => 'Innovation',
        'required'       => TRUE,
        'settings'       => [
          'handler'          => 'default:node',
          'handler_settings' => ['target_bundles' => ['solution' => 'solution']],
        ],
      ])->save();
    }
  }

  private function ensureStringField(string $bundle, string $field_name, string $label): void {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name'  => $field_name,
        'entity_type' => 'node',
        'type'        => 'string_long',
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        // 'settings'    => ['max_length' => 1024],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, $field_name)) {
      FieldConfig::create([
        'field_name'  => $field_name,
        'entity_type' => 'node',
        'bundle'      => $bundle,
        'label'       => $label,
      ])->save();
    }
  }

  private function ensureTermRefField(string $bundle, string $field_name, string $vid, string $label): void {
    if (!FieldStorageConfig::loadByName('node', $field_name)) {
      FieldStorageConfig::create([
        'field_name'  => $field_name,
        'entity_type' => 'node',
        'type'        => 'entity_reference',
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        'settings'    => ['target_type' => 'taxonomy_term'],
      ])->save();
    }
    if (!FieldConfig::loadByName('node', $bundle, $field_name)) {
      FieldConfig::create([
        'field_name'  => $field_name,
        'entity_type' => 'node',
        'bundle'      => $bundle,
        'label'       => $label,
        'settings'    => [
          'handler'          => 'default:taxonomy_term',
          'handler_settings' => ['target_bundles' => [$vid => $vid]],
        ],
      ])->save();
    }
  }

  private function ensureVocabulary(string $vid, string $name): void {
    if (!Vocabulary::load($vid)) {
      Vocabulary::create(['vid' => $vid, 'name' => $name])->save();
      $this->logger()->notice("Created vocabulary: $vid");
    }
  }

  /**
   * Maximum length for taxonomy term names.
   *
   * taxonomy_term_field_data.name is varchar(255).  We cap at 240 to leave a
   * safety margin for multi-byte UTF-8: mb_substr counts codepoints, but MySQL
   * varchar(255) counts bytes.  Without this guard, free-text annotation values
   * longer than 255 bytes crash with SQLSTATE[22001] String data right truncated.
   */
  /**
   * Heuristic: would treating these values as taxonomy terms make sense?
   *
   * A key is considered "free text" — and therefore unsuitable for a vocabulary
   * — when any of its values across ALL records exceeds TERM_NAME_MAX characters,
   * OR when the average value length exceeds 80 characters (typical of sentence-
   * or paragraph-length annotations like "measurable_positive_outcomes").
   *
   * This avoids both the varchar(255) crash AND polluting vocabularies with
   * unique long strings that provide no filtering value.
   *
   * @param mixed $values  The raw value(s) for this key across all records.
   */
  private function keyLooksLikeFreeText(mixed $values): bool {
    $flat = [];
    $arr = (array) $values;
    array_walk_recursive($arr, function ($v) use (&$flat) {
      $flat[] = (string) $v;
    });
    if (empty($flat)) {
      return FALSE;
    }
    $total_len  = 0;
    $count      = 0;
    foreach ($flat as $v) {
      $len = mb_strlen($v);
      if ($len > self::TERM_NAME_MAX) {
        return TRUE;   // any single value over the hard limit → free text
      }
      $total_len += $len;
      $count++;
    }
    $avg = $count > 0 ? $total_len / $count : 0;
    return $avg > 80;  // average sentence-length → free text
  }

  private const TERM_NAME_MAX = 240;

  private function ensureTerm(string $vid, string $name): int {
    if (mb_strlen($name) > self::TERM_NAME_MAX) {
      $this->logger()->warning(
        "Term name truncated in vocab '$vid' ("
        . mb_strlen($name) . " chars): " . mb_substr($name, 0, 80) . '...'
      );
      $name = mb_substr($name, 0, self::TERM_NAME_MAX);
    }

    $existing = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $vid, 'name' => $name]);
    if ($existing) {
      return (int) reset($existing)->id();
    }
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return (int) $term->id();
  }

  private function getTermId(string $vid, string $name): int {
    return $this->ensureTerm($vid, $name);
  }

  private function loadSolutionByOriginalId(string $original_id): ?Node {
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => 'solution', 'field_original_internal_id' => $original_id]);
    return $nodes ? reset($nodes) : NULL;
  }

  private function slug(string $s): string {
    return preg_replace('/[^a-z0-9_]/', '_', strtolower($s));
  }

  private function ensureOgAudience(string $bundle): void {
    if (!FieldConfig::loadByName('node', $bundle, \Drupal\og\OgGroupAudienceHelperInterface::DEFAULT_FIELD)) {
      \Drupal\og\Og::createField(
        \Drupal\og\OgGroupAudienceHelperInterface::DEFAULT_FIELD,
        'node',
        $bundle
      );
      $this->logger()->notice("Created og_audience on bundle: $bundle");
    }
  }
}