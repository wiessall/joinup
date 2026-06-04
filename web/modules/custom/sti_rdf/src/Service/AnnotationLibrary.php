<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Database\Connection;

/**
 * Query layer over the EAV annotation_value table.
 *
 * Three jobs:
 *   1. Schema discovery — what fields does a given collection actually use?
 *   2. Facet computation — for a (collection, field), what are the distinct
 *      values and their counts, optionally constrained by other active
 *      filters?
 *   3. Target resolution — given a set of filters, which target_entity_ids
 *      satisfy the AND-across-fields / OR-within-field semantics?
 *
 * Everything is driven from the data — no per-collection config required.
 * Add a collection by indexing annotations for it; it appears.
 */
final class AnnotationLibrary {

  private const TABLE = 'annotation_value';

  public function __construct(
    protected readonly Connection $database,
  ) {}

  /**
   * Lists every collection that has at least one indexed annotation.
   *
   * @return string[]
   */
  public function listCollections(): array {
    return $this->database->select(self::TABLE, 'av')
      ->fields('av', ['collection_id'])
      ->distinct()
      ->orderBy('collection_id')
      ->execute()
      ->fetchCol();
  }

  /**
   * Returns the field machine names that a collection has annotated values for.
   *
   * Fields appear in descending order of how many target entities use them,
   * so the most-populated facets surface first in the UI.
   *
   * @return string[]
   */
  public function getSchema(string $collectionId): array {
    $q = $this->database->select(self::TABLE, 'av');
    $q->addField('av', 'field_name');
    $q->addExpression('COUNT(DISTINCT av.target_entity_id)', 'usage_count');
    $q->condition('av.collection_id', $collectionId);
    $q->groupBy('av.field_name');
    $q->orderBy('usage_count', 'DESC');
    $q->orderBy('av.field_name', 'ASC');
    return $q->execute()->fetchCol();
  }

  /**
   * Counts unique target entities in the collection, after applying filters.
   */
  public function countTargets(string $collectionId, array $filters = []): int {
    $ids = $this->findTargetEntityIds($collectionId, $filters);
    return count($ids);
  }

  /**
   * Computes facet values + counts for one field in a collection.
   *
   * Convention: when computing facets for field X, we apply all OTHER filters
   * (everything except X). This is the standard "show me what else is
   * available given my current selection" UX, and it lets users build up
   * filters without one selection prematurely killing the other facets.
   *
   * @param array<string, string[]> $allFilters
   *   The complete filter state (field_name => list of selected value keys).
   *
   * @return list<array{value: string, label: string, count: int}>
   */
  public function getFacetValues(string $collectionId, string $fieldName, array $allFilters = []): array {
    $other = $allFilters;
    unset($other[$fieldName]);

    $targetIds = NULL;
    if (!empty($other)) {
      $targetIds = $this->findTargetEntityIds($collectionId, $other);
      if (empty($targetIds)) {
        return [];
      }
    }

    $q = $this->database->select(self::TABLE, 'av');
    $q->addField('av', 'value_key', 'value');
    // value_label can vary by row (e.g. translated labels); pick any one.
    $q->addExpression('MIN(av.value_label)', 'label');
    $q->addExpression('COUNT(DISTINCT av.target_entity_id)', 'count');
    $q->condition('av.collection_id', $collectionId);
    $q->condition('av.field_name', $fieldName);
    if ($targetIds !== NULL) {
      $q->condition('av.target_entity_id', $targetIds, 'IN');
    }
    $q->groupBy('av.value_key');
    $q->orderBy('count', 'DESC');
    $q->orderBy('label', 'ASC');

    $rows = $q->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(
      static fn(array $r): array => [
        'value' => (string) $r['value'],
        'label' => (string) ($r['label'] ?? $r['value']),
        'count' => (int) $r['count'],
      ],
      $rows,
    );
  }

  /**
   * Finds target_entity_ids satisfying the filter set.
   *
   * Semantics:
   *   - within one field: OR across selected values
   *   - across fields: AND
   *
   * Implementation: per filtered field, fetch the target_entity_id set, then
   * intersect. With proper indexes this is fast even for millions of rows
   * because each per-field query is a covering index scan.
   *
   * @param array<string, string[]> $filters
   *
   * @return int[]
   */
  public function findTargetEntityIds(string $collectionId, array $filters): array {
    // Drop empty buckets so an unselected facet doesn't filter anything.
    $filters = array_filter($filters, static fn($v) => !empty($v));

    if (empty($filters)) {
      return array_map('intval', $this->database->select(self::TABLE, 'av')
        ->fields('av', ['target_entity_id'])
        ->condition('collection_id', $collectionId)
        ->distinct()
        ->execute()
        ->fetchCol());
    }

    $sets = [];
    foreach ($filters as $fieldName => $values) {
      $ids = $this->database->select(self::TABLE, 'av')
        ->fields('av', ['target_entity_id'])
        ->condition('collection_id', $collectionId)
        ->condition('field_name', $fieldName)
        ->condition('value_key', array_values($values), 'IN')
        ->distinct()
        ->execute()
        ->fetchCol();
      if (empty($ids)) {
        // Empty set short-circuits the intersection.
        return [];
      }
      $sets[] = array_flip($ids);
    }

    // Intersect.
    $result = array_shift($sets);
    foreach ($sets as $set) {
      $result = array_intersect_key($result, $set);
      if (empty($result)) {
        return [];
      }
    }
    return array_map('intval', array_keys($result));
  }

  /**
   * Returns target_entity_type for a collection (assumes one type per coll.).
   *
   * Most collections will have a single target type (e.g. node:solution); we
   * pick the most common one if there's variance.
   */
  public function getTargetEntityType(string $collectionId): ?string {
    $q = $this->database->select(self::TABLE, 'av');
    $q->addField('av', 'target_entity_type');
    $q->addExpression('COUNT(*)', 'c');
    $q->condition('av.collection_id', $collectionId);
    $q->groupBy('av.target_entity_type');
    $q->orderBy('c', 'DESC');
    $q->range(0, 1);
    $r = $q->execute()->fetchField();
    return $r !== FALSE ? (string) $r : NULL;
  }

}
