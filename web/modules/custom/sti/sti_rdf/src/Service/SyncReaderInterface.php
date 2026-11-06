<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

/**
 * Reads rdf_sync mappings as plain data.
 *
 * The rdf_sync module keeps a bundle's class and base-field mappings on the
 * bundle config entity, and may keep configurable-field mappings on the field
 * config. Code that needs to know "which field emits which predicate" goes
 * through this interface instead of reading third-party settings itself, so
 * that the two locations are read the same way everywhere and tests can
 * substitute a mapping without enabling triplestore writes.
 */
interface SyncReaderInterface {

  /**
   * Returns every mapped bundle of the given content entity types.
   *
   * @param string[] $entityTypeIds
   *   Content entity type ids, e.g. ['node', 'paragraph'].
   *
   * @return list<array{entity_type: string, bundle: string, label: string, type: string}>
   *   One entry per bundle that declares an rdf_sync class.
   */
  public function syncedBundles(array $entityTypeIds): array;

  /**
   * Returns the field mappings of one bundle.
   *
   * @return list<array{field: string, column: string, predicate: string, type: string|null, label: string, description: string}>
   *   One entry per mapped field column, bundle-level entries first.
   */
  public function fieldSyncs(string $entityTypeId, string $bundle): array;

}
