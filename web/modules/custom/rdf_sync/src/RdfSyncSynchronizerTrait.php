<?php

declare(strict_types = 1);

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
   */
  protected function synchronize(array $entities, string $method): void {
    assert($this instanceof RdfSyncSynchronizer || $this instanceof RdfSyncSynchronizerWorker);
    assert(in_array($method, ['insert', 'update', 'delete'], TRUE));

    $insert = $delete = $where = '';

    if (in_array($method, ['insert', 'update'])) {
      $triples = '';
      foreach ($entities as $entity) {
        $triples .= $this->serializer->serialize($entity, 'ntriples');
      }
      $insert = "INSERT {\n$triples\n}";
    }
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
      $delete = "DELETE { ?subject ?predicate ?object . }";
      $where = "WHERE {\n?subject ?predicate ?object .\nFILTER(?subject IN (\n$uris\n)) .\n}";
    }

    $graphUri = RdfSyncConnection::GRAPH;
    $query = <<<QUERY
      WITH <$graphUri>
      $delete
      $insert
      $where
      QUERY;

    RdfSyncConnection::update($query);
  }

}
