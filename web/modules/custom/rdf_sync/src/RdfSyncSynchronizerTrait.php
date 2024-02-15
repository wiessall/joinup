<?php

declare(strict_types=1);

namespace Drupal\rdf_sync;

use Drupal\rdf_sync\Plugin\QueueWorker\RdfSyncSynchronizerWorker;
use EasyRdf\Serialiser\Ntriples;

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
   *
   * @todo Is it possible to run the update in a single query?
   */
  protected function doSynchronize(array $entities, string $method): void {
    assert($this instanceof RdfSyncSynchronizer || $this instanceof RdfSyncSynchronizerWorker);
    assert(in_array($method, ['insert', 'update', 'delete'], TRUE));

    $graphUri = RdfSyncConnection::GRAPH;

    if (in_array($method, ['update', 'delete'], TRUE)) {
      $rdfSerializer = new Ntriples();
      $uris = [];
      foreach ($entities as $entity) {
        $uris[] = $rdfSerializer->serialiseValue([
          'value' => $entity->getUri(),
          'type' => 'uri',
        ]);
      }
      $uris = implode(",\n", $uris);
      $query = <<<QUERY
        WITH <$graphUri>
        DELETE  { ?subject ?predicate ?object . }
        WHERE {
        ?subject ?predicate ?object .
        FILTER(?subject IN (
        $uris
        )) .
        }
        QUERY;
      RdfSyncConnection::update($query);
    }

    if (in_array($method, ['insert', 'update'])) {
      $triples = '';
      foreach ($entities as $entity) {
        $triples .= $this->serializer->serialize($entity, 'ntriples');
      }
      $query = <<<QUERY
        WITH <$graphUri>
        INSERT {
        $triples
        }
        QUERY;
      RdfSyncConnection::update($query);
    }
  }

}
