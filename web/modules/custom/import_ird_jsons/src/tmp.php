<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;
use Drush\Commands\DrushCommands;

/**
 * Imports per-collection field claims from a normalized IRD JSON.
 *
 * SELF-CONTAINED VARIANT: classification (categorical vs literal) and URI
 * minting are done in PHP from the raw _annotations. Use this when the IRD
 * Python library does NOT yet pre-resolve those decisions. Once Python emits a
 * fully-resolved JSON (categorical signalled by value_uri, predicate/value URIs
 * provided), switch to the lean IrdAnnotateCommands.php instead.
 *
 * Each value a source asserts about a solution becomes one `claims` paragraph
 * attached to that solution's `field_claims`. A claim is a reified statement:
 *
 *   solution  sti:hasFieldClaim  claim
 *   claim     sti:claimProperty  <property-uri>          (the predicate)
 *   claim     sti:claimLiteralValue "…"  | claimRefValue <value-uri>  (the object)
 *   claim     sti:claimedBy      <collection-uri>        (provenance)
 *   claim     sti:claimedAt      "…"^^xsd:dateTime
 *
 * Predicates and categorical values live in two shared, hierarchical
 * vocabularies (parent term = collection id):
 *
 *   claim_properties:  {collection} → {key}
 *   claim_values:      {collection} → {key} → {value}
 *
 * Term URIs are written to the property-uri field so rdf_sync emits them as
 * resources rather than entity-id-based URIs.
 *
 * The JSON shape (IRD lib normalized format):
 *
 *   {
 *     "meta": { "collection_id": "stf", "collection_nid": 42 },
 *     "records": [
 *       {
 *         "id": "stf_001",
 *         "title": "…",
 *         "_annotations": {
 *           "organization_type": "Non-profit",
 *           "technologies": ["GIS", "Remote sensing"],
 *           "custom_fields": { "readiness_level": "TRL 7" }
 *         }
 *       }
 *     ]
 *   }
 *
 * Re-running for a collection is idempotent: a solution's existing claims for
 * THIS collection are cleared and rebuilt; claims from other collections are
 * left untouched (claims from different sources coexist as equals).
 *
 * Usage:
 *   drush ird:annotate stf path/to/stf.json
 *   drush ird:annotate stf path/to/stf.json --dry-run
 */
final class IrdAnnotateCommands extends DrushCommands {

  private const VOCAB_PROPERTIES = 'claim_properties';
  private const VOCAB_VALUES = 'claim_values';
  private const PARAGRAPH_BUNDLE = 'claims';
  private const SOLUTION_BUNDLE = 'solution';

  /**
   * Field on a vocabulary term holding its canonical RDF URI.
   *
   * rdf_sync reads this (via the vocabulary's uri_field_name / uri_plugin) to
   * emit the term as a URI resource instead of an entity-id-based URI.
   */
  private const FIELD_TERM_URI = 'field_property_uri';

  /**
   * Base namespace for minted concept URIs.
   */
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
   *   Absolute or relative path to the normalized JSON.
   *
   * @option dry-run
   *   Report discovered keys and counts; write nothing.
   */
  public function annotate(
    string $collection_id,
    string $json_path,
  ): void {
    $dry = (bool) $options['dry-run'];

    $data = json_decode(file_get_contents($json_path), TRUE, 512, JSON_THROW_ON_ERROR);
    $records = $data['records'] ?? [];
    $collection_nid = $data['meta']['collection_nid'] ?? NULL;

    if (!$collection_nid) {
      throw new \InvalidArgumentException('meta.collection_nid is required — it is the claimedBy target.');
    }

    // Ensure the collection's namespace term exists in both vocabularies.
    $properties_parent = $this->ensureTerm(self::VOCAB_PROPERTIES, $collection_id);
    $values_parent = $this->ensureTerm(self::VOCAB_VALUES, $collection_id);

    $created = 0;
    $skipped = 0;

    foreach ($records as $record) {
      $solution = $this->loadSolution($record['id']);
      if (!$solution) {
        $this->logger()->warning(sprintf('No solution for id=%s — skipping.', $record['id']));
        $skipped++;
        continue;
      }

      // Idempotency: drop this collection's previous claims, keep others.
      $this->clearCollectionClaims($solution, (int) $collection_nid);

      $annotations = $this->flattenAnnotations($record['_annotations'] ?? []);
      foreach ($annotations as $key => $value) {
        $property_term = $this->ensureKeyTerm(self::VOCAB_PROPERTIES, $collection_id, $key, $properties_parent);

        foreach ($this->normalizeValues($value) as $single) {
          $paragraph = $classification[$key]
            ? $this->buildRefClaim($collection_id, $key, $single, $property_term, $values_parent, (int) $collection_nid)
            : $this->buildLiteralClaim($single, $property_term, (int) $collection_nid);

          $solution->get('field_claims')->appendItem([
            'target_id' => $paragraph->id(),
            'target_revision_id' => $paragraph->getRevisionId(),
          ]);
          $created++;
        }
      }

      $solution->save();
    }

    $this->logger()->success(sprintf('%d claims created, %d records skipped.', $created, $skipped));
  }

  /**
   * Remove all claims for a collection.
   *
   * Deletes every `claims` paragraph whose claimedBy is the collection, detaches
   * them from their host solutions, and (unless --keep-terms) removes the
   * collection's terms from both vocabularies. Solutions and other collections'
   * claims are left untouched.
   *
   * @command ird:rollback-annotation
   *
   * @param string $collection_id
   *   Machine-name slug for the collection (e.g. "stf").
   *
   * @option dry-run
   *   Report what would be deleted; delete nothing.
   * @option keep-terms
   *   Leave the collection's vocabulary terms in place.
   */
  public function rollbackAnnotation(
    string $collection_id,
    array $options = ['dry-run' => FALSE, 'keep-terms' => FALSE],
  ): void {
    $dry = (bool) $options['dry-run'];
    $keep_terms = (bool) $options['keep-terms'];

    $collection_nid = $this->collectionNidBySlug($collection_id);
    if (!$collection_nid) {
      throw new \InvalidArgumentException(sprintf('No collection node found for "%s".', $collection_id));
    }

    // Collect host solutions of this collection's claims, then rebuild each
    // solution's field_claims once (cheaper than per-paragraph saves).
    $paragraph_ids = $this->paragraphStorage()->getQuery()
      ->condition('type', self::PARAGRAPH_BUNDLE)
      ->condition('field_claimed_by', $collection_nid)
      ->accessCheck(FALSE)
      ->execute();

    $this->logger()->notice(sprintf('%d claim paragraphs to remove.', count($paragraph_ids)));

    if (!$dry && $paragraph_ids) {
      $solutions = [];
      foreach ($this->paragraphStorage()->loadMultiple($paragraph_ids) as $paragraph) {
        $host = $paragraph->getParentEntity();
        if ($host instanceof NodeInterface) {
          $solutions[$host->id()] = $host;
        }
      }
      foreach ($solutions as $solution) {
        $this->clearCollectionClaims($solution, (int) $collection_nid);
        $solution->save();
      }
      $this->logger()->notice('Detached and deleted claim paragraphs.');
    }

    if (!$keep_terms) {
      foreach ([self::VOCAB_PROPERTIES, self::VOCAB_VALUES] as $vid) {
        $parent = $this->findTerm($vid, $collection_id);
        if (!$parent) {
          continue;
        }
        $descendants = $this->termStorage()->loadTree($vid, (int) $parent->id());
        $count = count($descendants) + 1;
        $this->logger()->notice(sprintf('%d terms to remove from %s.', $count, $vid));
        if (!$dry) {
          foreach ($descendants as $item) {
            $this->termStorage()->load($item->tid)?->delete();
          }
          $parent->delete();
        }
      }
    }

    if ($dry) {
      $this->logger()->notice('Dry run — nothing deleted.');
      return;
    }
    $this->logger()->success(sprintf('Rollback complete for "%s".', $collection_id));
  }

  // ---------------------------------------------------------------------------
  // Claim construction
  // ---------------------------------------------------------------------------

  /**
   * Build a literal-valued claim paragraph (saved, ready to attach).
   */
  private function buildLiteralClaim(string $value, TermInterface $property, int $collection_nid): ParagraphInterface {
    return $this->createClaim([
      'field_claim_property' => ['target_id' => $property->id()],
      'field_claim_literal_value' => $value,
    ], $collection_nid);
  }

  /**
   * Build a reference-valued claim paragraph (saved, ready to attach).
   *
   * Ensures the value term exists under {collection} → {key} in claim_values.
   */
  private function buildRefClaim(
    string $collection_id,
    string $key,
    string $value,
    TermInterface $property,
    TermInterface $values_parent,
    int $collection_nid,
  ): ParagraphInterface {
    $key_term = $this->ensureKeyTerm(self::VOCAB_VALUES, $collection_id, $key, $values_parent);
    $value_term = $this->ensureTerm(
      self::VOCAB_VALUES,
      $value,
      (int) $key_term->id(),
      $this->valueUri($collection_id, $key, $value),
    );

    return $this->createClaim([
      'field_claim_property' => ['target_id' => $property->id()],
      'field_claim_ref_value' => ['target_id' => $value_term->id()],
    ], $collection_nid);
  }

  /**
   * Create and save a claims paragraph with shared provenance fields.
   */
  private function createClaim(array $fields, int $collection_nid): ParagraphInterface {
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $this->paragraphStorage()->create([
      'type' => self::PARAGRAPH_BUNDLE,
      'field_claimed_by' => ['target_id' => $collection_nid],
      'field_claimed_at' => $this->time->getRequestTime(),
    ] + $fields);
    $paragraph->save();
    return $paragraph;
  }

  /**
   * Delete a solution's claim paragraphs that were claimed by a collection.
   *
   * Mutates field_claims in memory; caller is responsible for saving the
   * solution.
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
      $kept[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }
    $solution->set('field_claims', $kept);
  }

  // ---------------------------------------------------------------------------
  // Taxonomy helpers (idempotent)
  // ---------------------------------------------------------------------------

  /**
   * Ensure a key term exists under a collection namespace term.
   *
   * For claim_properties this also sets the predicate URI; for claim_values the
   * key level is structural and carries no URI of its own.
   */
  private function ensureKeyTerm(string $vid, string $collection_id, string $key, TermInterface $parent): TermInterface {
    $uri = $vid === self::VOCAB_PROPERTIES ? $this->propertyUri($collection_id, $key) : NULL;
    return $this->ensureTerm($vid, $key, (int) $parent->id(), $uri);
  }

  /**
   * Ensure a term exists (by vocabulary, name, and parent) and return it.
   */
  private function ensureTerm(string $vid, string $name, ?int $parent_tid = NULL, ?string $uri = NULL): TermInterface {
    $name = $this->truncateTermName($name, $vid);

    if ($existing = $this->findTerm($vid, $name, $parent_tid)) {
      return $existing;
    }

    $values = ['vid' => $vid, 'name' => $name];
    if ($parent_tid !== NULL) {
      $values['parent'] = [$parent_tid];
    }

    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $this->termStorage()->create($values);
    if ($uri !== NULL && $term->hasField(self::FIELD_TERM_URI)) {
      $term->set(self::FIELD_TERM_URI, $uri);
    }
    $term->save();
    return $term;
  }

  /**
   * Find a term by vocabulary, name, and (optionally) parent.
   */
  private function findTerm(string $vid, string $name, ?int $parent_tid = NULL): ?TermInterface {
    $properties = ['vid' => $vid, 'name' => $this->truncateTermName($name, $vid)];
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
   * Flatten _annotations, hoisting custom_fields sub-keys to the top level.
   */
  private function flattenAnnotations(array $annotations): array {
    $flat = [];
    foreach ($annotations as $key => $value) {
      if ($key === 'custom_fields' && is_array($value)) {
        $flat += $value;
        continue;
      }
      $flat[$key] = $value;
    }
    return $flat;
  }

  /**
   * Normalize a raw annotation value into a flat list of scalar strings.
   *
   * Handles scalars, plain arrays, and arrays of {value: …} objects.
   *
   * @return string[]
   */
  private function normalizeValues(mixed $value): array {
    if ($value === NULL || $value === '') {
      return [];
    }
    $items = is_array($value) ? $value : [$value];

    $flat = [];
    foreach ($items as $item) {
      if (is_array($item)) {
        $flat[] = (string) ($item['value'] ?? reset($item));
      }
      else {
        $flat[] = (string) $item;
      }
    }
    return array_values(array_filter($flat, static fn(string $v): bool => $v !== ''));
  }

  private function loadSolution(string $original_id): ?NodeInterface {
    $matches = $this->nodeStorage()->loadByProperties([
      'type' => self::SOLUTION_BUNDLE,
      'field_original_internal_id' => $original_id,
    ]);
    return $matches ? reset($matches) : NULL;
  }

  private function collectionNidBySlug(string $collection_id): ?int {
    // Adjust the matching field if collections are keyed differently.
    $matches = $this->nodeStorage()->loadByProperties([
      'type' => 'collection',
      'field_original_internal_id' => $collection_id,
    ]);
    return $matches ? (int) reset($matches)->id() : NULL;
  }

  // ---------------------------------------------------------------------------
  // URI minting
  // ---------------------------------------------------------------------------

  private function propertyUri(string $collection_id, string $key): string {
    return self::URI_BASE . $this->slug($collection_id) . '#' . $this->slug($key);
  }

  private function valueUri(string $collection_id, string $key, string $value): string {
    return self::URI_BASE . $this->slug($collection_id) . '/' . $this->slug($key) . '#' . $this->slug($value);
  }

  private function slug(string $value): string {
    return trim(preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower($value)), '_');
  }

  // ---------------------------------------------------------------------------
  // Storage accessors
  // ---------------------------------------------------------------------------

  private function nodeStorage(): \Drupal\Core\Entity\EntityStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

  private function termStorage(): \Drupal\taxonomy\TermStorageInterface {
    return $this->entityTypeManager->getStorage('taxonomy_term');
  }

  private function paragraphStorage(): \Drupal\Core\Entity\EntityStorageInterface {
    return $this->entityTypeManager->getStorage('paragraph');
  }

}