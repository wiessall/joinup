<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\sti_rdf\Service\SyncReaderInterface;

/**
 * Stands in for rdf_sync mappings in kernel tests.
 *
 * Real mappings live in bundle config that rdf_sync acts on; with them in
 * place every node save would try to reach a triplestore. Tests declare the
 * field-to-predicate map here instead.
 */
final class TestSyncReader implements SyncReaderInterface {

  /**
   * Predicates per entity type, bundle and field.
   *
   * @var array<string, array<string, array<string, string>>>
   */
  public static array $predicates = [];

  /**
   * {@inheritdoc}
   */
  public function syncedBundles(array $entityTypeIds): array {
    $out = [];
    foreach ($entityTypeIds as $entityTypeId) {
      foreach (array_keys(self::$predicates[$entityTypeId] ?? []) as $bundle) {
        $out[] = [
          'entity_type' => $entityTypeId,
          'bundle' => $bundle,
          'label' => $bundle,
          'type' => 'urn:test:' . $bundle,
        ];
      }
    }
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSyncs(string $entityTypeId, string $bundle): array {
    $out = [];
    foreach (self::$predicates[$entityTypeId][$bundle] ?? [] as $field => $predicate) {
      $out[] = [
        'field' => $field,
        'column' => 'target_id',
        'predicate' => $predicate,
        'type' => 'resource',
        'label' => $field,
        'description' => '',
      ];
    }
    return $out;
  }

}
