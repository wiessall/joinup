<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Updates the RDF backend.
 *
 * @todo In ISAICP-8409, implement a switch that allows to switch to a delayed
 *   update using a queue and a worker, so that:
 *   - In local development and CI pipeline, use instant update so the tests are
 *     getting the results in real-time. Performance is not an issue.
 *   - In production set the switch to delayed update, so we offload the current
 *     operations. It could be useful to be able to action the switch from UI in
 *     production so that we can do debugging when it's the case.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8409
 */
class RdfSyncUpdater {

  /**
   * Constructs a new service instance.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   */
  public function __construct(protected SerializerInterface $serializer) {}

  /**
   * Inserts an entity in the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity for which to update the RDF backend.
   */
  public function insert(RdfSyncEntityInterface $entity): void {
    $this->doUpdate($entity, 'insert');
  }

  /**
   * Updates an entity in the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity for which to update the RDF backend.
   */
  public function update(RdfSyncEntityInterface $entity): void {
    $this->doUpdate($entity, 'insert');
  }

  /**
   * Deletes an entity from the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity for which to update the RDF backend.
   */
  public function delete(RdfSyncEntityInterface $entity): void {
    $graph = RdfSyncConnection::GRAPH;
    $query = <<<QUERY
      WITH <$graph>
      DELETE { <{$entity->id()}> ?predicate ?object . }
      WHERE { <{$entity->id()}> ?predicate ?object . }
      QUERY;
    RdfSyncConnection::update($query);
  }

  /**
   * Provides a helper to insert/update trhe RDF backend given an entity.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity for which to update the RDF backend.
   * @param string $method
   *   Method: 'insert' or 'update'.
   */
  protected function doUpdate(RdfSyncEntityInterface $entity, string $method): void {
    assert(in_array($method, ['insert', 'update'], TRUE));

    $graph = RdfSyncConnection::GRAPH;
    $triples = $this->serializer->serialize($entity, 'ntriples');

    $delete = $where = '';
    if ($method === 'update') {
      $delete = "DELETE { <{$entity->id()}> ?predicate ?object . }";
      $where = "WHERE { <{$entity->id()}> ?predicate ?object . }";
    }

    $query = <<<QUERY
      WITH <{$graph}>
      {$delete}
      INSERT {
        {$triples}
      }
      {$where}
      QUERY;

    RdfSyncConnection::update($query);
  }

}
