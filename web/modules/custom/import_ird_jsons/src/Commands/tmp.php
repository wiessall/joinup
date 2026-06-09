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