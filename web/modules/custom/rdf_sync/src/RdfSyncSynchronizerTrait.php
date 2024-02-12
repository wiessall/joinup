<?php

declare(strict_types=1);

namespace Drupal\rdf_sync;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Plugin\QueueWorker\RdfSyncSynchronizerWorker;

/**
 * Reusable code between RDF synchronizer service and plugin.
 */
trait RdfSyncSynchronizerTrait {

  /**
   * Synchronizes the RDF backend given a list of entities.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities
   *   The entities for which to update the RDF backend.
   * @param string $method
   *   Method: 'insert', 'update' or 'delete'.
   */
  protected function doSynchronize(array $entities, string $method): void {
    assert($this instanceof RdfSyncSynchronizer || $this instanceof RdfSyncSynchronizerWorker);
    assert(in_array($method, ['insert', 'update', 'delete'], TRUE));

    if (in_array($method, ['update', 'delete'], TRUE)) {
      $uris = array_map(
        fn(RdfSyncEntityInterface $entity): string => $entity->getUri(),
        $entities,
      );
      RdfSyncConnection::deleteTriples($uris);
    }

    if (in_array($method, ['insert', 'update'])) {
      $normalizer = \Drupal::getContainer()->get('rdf_sync.normalizer');
      $triples = [];
      foreach ($entities as $entity) {
        $triples += $normalizer->normalize($entity);
      }
      RdfSyncConnection::insertTriples($triples);
    }
  }

}
