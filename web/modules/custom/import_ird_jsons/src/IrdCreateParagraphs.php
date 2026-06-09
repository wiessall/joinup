<?php

declare(strict_types=1);

namespace Drupal\import_IRD_jsons\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;
use Drush\Commands\DrushCommands;

/**
 * Materializes field claims from a fully-resolved IRD JSON.
 *
 * All decisions (categorical-vs-literal, predicate URIs, value URIs, name
 * normalization) are made upstream in the IRD Python library. This command
 * only ensures terms exist and builds the paragraphs — it decides nothing.
 *
 * Each claim becomes one `claims` paragraph on the solution's `field_claims`.
 * A claim carrying `value_uri` is a reference (claim_values term); otherwise it
 * is a literal. Predicates and categorical values live in two hierarchical
 * vocabularies, parent term = collection id:
 *   claim_properties:  {collection} → {property}
 *   claim_values:       {collection} → {property} → {value}
 *
 * JSON shape:
 *   {
 *     "meta": { "collection_id": "stf", "collection_nid": 42 },
 *     "records": [
 *       {
 *         "id": "stf_001",
 *         "claims": [
 *           { "property": "organization_type",
 *             "property_uri": "http://data.fao.org/ird/stf#organization_type",
 *             "value": "Non-profit",
 *             "value_uri": "http://data.fao.org/ird/stf/organization_type#non_profit" },
 *           { "property": "readiness_level",
 *             "property_uri": "http://data.fao.org/ird/stf#readiness_level",
 *             "value": "TRL 7" }
 *         ]
 *       }
 *     ]
 *   }
 *
 * Idempotent: re-running clears only this collection's claims per solution.
 */
final class IrdAnnotateCommands extends DrushCommands {

  private const VOCAB_PROPERTIES = 'claim_properties';
  private const VOCAB_VALUES = 'claim_values';
  private const FIELD_TERM_URI = 'field_property_uri';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
  ) {
    parent::__construct();
  }

  /**
   * Import field claims for a collection.
   *
   * @command ird:annotate
   *
   * @param string $json_path
   *   Path to the resolved IRD JSON.
   */
  public function annotate(string $json_path): void {
    $data = json_decode(file_get_contents($json_path), TRUE, 512, JSON_THROW_ON_ERROR);
    $collection_id = $data['meta']['collection_id'];
    $collection_nid = (int) $data['meta']['collection_nid'];

    $properties_parent = $this->ensureTerm(self::VOCAB_PROPERTIES, $collection_id);
    $values_parent = $this->ensureTerm(self::VOCAB_VALUES, $collection_id);

    $count = 0;
    foreach ($data['records'] as $record) {
      $solution = $this->loadSolution($record['id']);
      if (!$solution) {
        $this->logger()->warning("No solution for id={$record['id']} — skipping.");
        continue;
      }

      $this->clearCollectionClaims($solution, $collection_nid);

      foreach ($record['claims'] as $claim) {
        $property = $this->ensureTerm(
          self::VOCAB_PROPERTIES, $claim['property'], $properties_parent, $claim['property_uri'],
        );
        $fields = ['field_claim_property' => ['target_id' => $property->id()]];

        if (isset($claim['value_uri'])) { //! Value uri? Why do I need to provide this here?
          $key = $this->ensureTerm(self::VOCAB_VALUES, $claim['property'], $values_parent);
          $value = $this->ensureTerm(self::VOCAB_VALUES, $claim['value'], $key, $claim['value_uri']);
          $fields['field_claim_ref_value'] = ['target_id' => $value->id()];
        }
        else {
          $fields['field_claim_literal_value'] = $claim['value'];
        }

        $paragraph = $this->createClaim($fields, $collection_nid);
        $solution->get('field_claims')->appendItem([
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ]);
        $count++;
      }

      $solution->save();
    }

    $this->logger()->success("$count claims imported for '$collection_id'.");
  }

  /**
   * Remove all claims for a collection and its vocabulary terms.
   *
   * @command ird:rollback-annotation
   *
   * @param string $collection_id
   *   Collection slug (e.g. "stf").
   *
   * @option keep-terms Leave the collection's vocabulary terms in place.
   */
  public function rollbackAnnotation(string $collection_id, array $options = ['keep-terms' => FALSE]): void {
    $collection_nid = $this->collectionNid($collection_id);

    $ids = $this->paragraphStorage()->getQuery()
      ->condition('type', 'claims')
      ->condition('field_claimed_by', $collection_nid)
      ->accessCheck(FALSE)
      ->execute();

    $solutions = [];
    foreach ($this->paragraphStorage()->loadMultiple($ids) as $paragraph) {
      $host = $paragraph->getParentEntity();
      if ($host instanceof NodeInterface) {
        $solutions[$host->id()] = $host;
      }
    }
    foreach ($solutions as $solution) {
      $this->clearCollectionClaims($solution, $collection_nid);
      $solution->save();
    }

    if (!$options['keep-terms']) {
      foreach ([self::VOCAB_PROPERTIES, self::VOCAB_VALUES] as $vid) {
        if ($parent = $this->findTerm($vid, $collection_id)) {
          foreach ($this->termStorage()->loadTree($vid, (int) $parent->id()) as $child) {
            $this->termStorage()->load($child->tid)?->delete();
          }
          $parent->delete();
        }
      }
    }

    $this->logger()->success("Rolled back '$collection_id' (" . count($ids) . ' claims).');
  }

  /**
   * Create a saved claims paragraph with shared provenance fields.
   */
  private function createClaim(array $fields, int $collection_nid): ParagraphInterface {
    $paragraph = $this->paragraphStorage()->create([
      'type' => 'claims',
      'field_claimed_by' => ['target_id' => $collection_nid],
      'field_claimed_at' => $this->time->getRequestTime(),
    ] + $fields);
    $paragraph->save();
    return $paragraph;
  }

  /**
   * Delete a solution's claims for one collection (caller saves the solution).
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

  /**
   * Find-or-create a term by vocabulary, name, and parent; set its URI if given.
   */
  private function ensureTerm(string $vid, string $name, ?TermInterface $parent = NULL, ?string $uri = NULL): TermInterface {
    if ($existing = $this->findTerm($vid, $name, $parent)) {
      return $existing;
    }
    $term = $this->termStorage()->create([
      'vid' => $vid,
      'name' => $name,
      'parent' => $parent ? [$parent->id()] : [],
    ]);
    if ($uri !== NULL && $term->hasField(self::FIELD_TERM_URI)) {
      $term->set(self::FIELD_TERM_URI, $uri);
    }
    $term->save();
    return $term;
  }

  private function findTerm(string $vid, string $name, ?TermInterface $parent = NULL): ?TermInterface {
    $properties = ['vid' => $vid, 'name' => $name];
    if ($parent) {
      $properties['parent'] = $parent->id();
    }
    $matches = $this->termStorage()->loadByProperties($properties);
    return $matches ? reset($matches) : NULL;
  }

  private function loadSolution(string $original_id): ?NodeInterface {
    $matches = $this->nodeStorage()->loadByProperties([
      'type' => 'solution',
      'field_original_internal_id' => $original_id,
    ]);
    return $matches ? reset($matches) : NULL;
  }

  private function collectionNid(string $collection_id): int {
    $matches = $this->nodeStorage()->loadByProperties([
      'type' => 'collection',
      'field_original_internal_id' => $collection_id,
    ]);
    if (!$matches) {
      throw new \InvalidArgumentException("No collection node for '$collection_id'.");
    }
    return (int) reset($matches)->id();
  }

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