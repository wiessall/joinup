<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

/**
 * Helper methods to invalidate caches.
 */
trait CacheInvalidationTrait {

  /**
   * Invalidates the entity memory cache.
   *
   * Drupal caches entities in memory, by storing them in a simple PHP array. In
   * normal operation this cache is only valid during the duration of the
   * request. If a test scenario creates entities in the Behat PHP process but
   * then subsequently updates these entities by executing actions as a user in
   * the website, then the memory cache will be out of sync and needs to be
   * invalidated.
   *
   * @see \Drupal\Core\Cache\MemoryCache\MemoryCache
   */
  protected function invalidateEntityMemoryCache(): void {
    /** @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $entity_memory_cache */
    $entity_memory_cache = \Drupal::service('entity.memory_cache');
    $entity_memory_cache->deleteAll();
  }

}
