<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Kernel;

use Drupal\rdf_sync\Model\SyncMethod;
use Drupal\rdf_sync\RdfSyncSynchronizer;

/**
 * Stands in for rdf_sync's synchronizer: records writes, reaches no store.
 *
 * Keeps the real stack handling, including the behaviour the code under
 * test has to cope with: a failed flush leaves its stack in place.
 */
final class RecordingSynchronizer extends RdfSyncSynchronizer {

  /**
   * Entity ids written, one list per flush.
   *
   * @var array<int, int[]>
   */
  public static array $writes = [];

  /**
   * Entity ids whose presence in a flush makes it fail.
   *
   * @var int[]
   */
  public static array $poison = [];

  /**
   * Replaces the parent constructor: no dependencies are needed.
   */
  public function __construct() {}

  /**
   * {@inheritdoc}
   */
  public function synchronize(SyncMethod $method, array $entities, ?bool $synchronize = NULL): void {
    $this->stack[] = [$method, $entities];
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    foreach ($this->stack as [, $entities]) {
      $ids = array_map(static fn($entity): int => (int) $entity->id(), $entities);
      if (array_intersect($ids, self::$poison)) {
        throw new \RuntimeException('Store rejected ' . implode(',', array_intersect($ids, self::$poison)));
      }
      self::$writes[] = $ids;
    }
    $this->stack = [];
  }

  /**
   * Returns how many operations are still stacked.
   */
  public function pending(): int {
    return count($this->stack);
  }

}
