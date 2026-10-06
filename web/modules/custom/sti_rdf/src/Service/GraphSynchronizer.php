<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\rdf_sync\Model\SyncMethod;
use Drupal\rdf_sync\RdfSyncMapperInterface;
use Drupal\rdf_sync\RdfSyncSynchronizer;

/**
 * Writes existing entities to the graph in small, independent chunks.
 *
 * For rebuilding the graph. rdf_sync:synchronize slows down steadily on a
 * large entity type; this walks the ids in order, hands each chunk to
 * rdf_sync's own synchronizer, flushes it at once, and empties the entity
 * memory cache before the next one, so the cost per chunk stays flat.
 *
 * Nothing is saved: entities are loaded and sent. rdf_sync's event still
 * runs, so rdf_sync_published keeps unpublished content out of the graph.
 *
 * A chunk that fails is retried one entity at a time, so one entity
 * rdf_sync cannot write costs only itself and is reported by id.
 */
final class GraphSynchronizer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RdfSyncSynchronizer $synchronizer,
    private readonly RdfSyncMapperInterface $mapper,
    private readonly MemoryCacheInterface $memoryCache,
  ) {}

  /**
   * Counts the entities a run would cover.
   *
   * @param string $entityTypeId
   *   The entity type.
   * @param string[] $bundles
   *   Bundles to limit to; empty for all.
   * @param int $afterId
   *   Only ids greater than this.
   */
  public function count(string $entityTypeId, array $bundles = [], int $afterId = 0): int {
    return (int) $this->query($entityTypeId, $bundles, $afterId)->count()->execute();
  }

  /**
   * Synchronizes the next chunk of entities.
   *
   * @param string $entityTypeId
   *   The entity type.
   * @param string[] $bundles
   *   Bundles to limit to; empty for all.
   * @param int $afterId
   *   Process ids greater than this; 0 to start.
   * @param int $limit
   *   Entities per chunk.
   *
   * @return array{last_id: int, examined: int, synced: int, failed: array<int, string>}
   *   'last_id' is the cursor for the next call; 'examined' is 0 when there
   *   is nothing left; 'synced' counts mapped entities handed to rdf_sync
   *   without error (rdf_sync may still leave some out, e.g. unpublished
   *   ones); 'failed' maps entity id to the error message.
   */
  public function syncChunk(string $entityTypeId, array $bundles, int $afterId, int $limit = 50): array {
    $definition = $this->entityTypeManager->getDefinition($entityTypeId);
    $ids = $this->query($entityTypeId, $bundles, $afterId)
      ->sort((string) $definition->getKey('id'))
      ->range(0, $limit)
      ->execute();
    if (!$ids) {
      return ['last_id' => $afterId, 'examined' => 0, 'synced' => 0, 'failed' => []];
    }

    $entities = [];
    foreach ($this->entityTypeManager->getStorage($entityTypeId)->loadMultiple($ids) as $id => $entity) {
      if ($entity instanceof ContentEntityInterface && $this->mapper->isMappedEntity($entity)) {
        $entities[(int) $id] = $entity;
      }
    }

    $failed = [];
    if ($entities) {
      try {
        $this->flush($entities);
      }
      catch (\Throwable) {
        // Find out which entity it is; the others still get written.
        foreach ($entities as $id => $entity) {
          try {
            $this->flush([$entity]);
          }
          catch (\Throwable $e) {
            $failed[$id] = mb_substr($e->getMessage(), 0, 300);
          }
        }
      }
    }

    // Every loaded entity, and every entity it referenced while being
    // normalised, would otherwise stay in memory for the whole run.
    $this->memoryCache->deleteAll();

    return [
      'last_id' => (int) max($ids),
      'examined' => count($ids),
      'synced' => count($entities) - count($failed),
      'failed' => $failed,
    ];
  }

  /**
   * Sends entities to the graph now, replacing their triples.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $entities
   *   The entities.
   */
  private function flush(array $entities): void {
    try {
      $this->synchronizer->synchronize(SyncMethod::UPDATE, array_values($entities), TRUE);
      $this->synchronizer->destruct();
    }
    catch (\Throwable $e) {
      // destruct() empties its stack only after a clean pass. Left alone,
      // the failed chunk would be sent again with every later flush.
      // rdf_sync offers no reset, hence the bound closure.
      \Closure::bind(function (): void {
        $this->stack = [];
      }, $this->synchronizer, RdfSyncSynchronizer::class)();
      throw $e;
    }
  }

  /**
   * Builds the id query for an entity type, its bundles and a cursor.
   */
  private function query(string $entityTypeId, array $bundles, int $afterId): QueryInterface {
    $definition = $this->entityTypeManager->getDefinition($entityTypeId);
    $query = $this->entityTypeManager->getStorage($entityTypeId)->getQuery()
      ->accessCheck(FALSE)
      ->condition((string) $definition->getKey('id'), $afterId, '>');
    $bundleKey = $definition->getKey('bundle');
    if ($bundles && $bundleKey) {
      $query->condition((string) $bundleKey, $bundles, 'IN');
    }
    return $query;
  }

}
