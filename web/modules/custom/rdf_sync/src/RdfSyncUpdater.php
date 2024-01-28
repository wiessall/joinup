<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use EasyRdf\Serialiser\Ntriples;
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
   *   The entity to be inserted in the RDF backend.
   */
  public function insert(RdfSyncEntityInterface $entity): void {
    $this->insertMultiple([$entity]);
  }

  /**
   * Inserts a list of entities in the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities
   *   A list of entities to be inserted in the RDF backend.
   */
  public function insertMultiple(array $entities): void {
    $this->doUpdate($entities, 'insert');
  }

  /**
   * Updates an entity in the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity for which to update the RDF backend.
   */
  public function update(RdfSyncEntityInterface $entity): void {
    $this->updateMultiple([$entity]);
  }

  /**
   * Updates a list of entities in the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities
   *   A list of entities for which to update the RDF backend.
   */
  public function updateMultiple(array $entities): void {
    $this->doUpdate($entities, 'update');
  }

  /**
   * Deletes an entity from the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity
   *   The entity for which to update the RDF backend.
   */
  public function delete(RdfSyncEntityInterface $entity): void {
    $this->deleteMultiple([$entity]);
  }

  /**
   * Deletes a list of entities from the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities
   *   A list of entities being deleted.
   */
  public function deleteMultiple(array $entities): void {
    $graph = RdfSyncConnection::GRAPH;
    $uris = $this->getSerializedUris($entities, ",\n  ");
    $query = <<<QUERY
      WITH <$graph>
      DELETE { ?subject ?predicate ?object . }
      WHERE {
        ?subject ?predicate ?object .
        FILTER(?subject IN (
          $uris
        )) .
      }
      QUERY;
    RdfSyncConnection::update($query);
  }

  /**
   * Provides a helper to update the RDF backend given a list of entities.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities
   *   The entities for which to update the RDF backend.
   * @param string $method
   *   Method: 'insert' or 'update'.
   */
  protected function doUpdate(array $entities, string $method): void {
    assert(in_array($method, ['insert', 'update'], TRUE));

    $graphUri = RdfSyncConnection::GRAPH;

    $triples = '';
    foreach ($entities as $entity) {
      $triples .= $this->serializer->serialize($entity, 'ntriples');
    }

    $delete = $where = '';
    if ($method === 'update') {
      $uris = $this->getSerializedUris($entities, ",\n  ");
      $delete = "DELETE { ?subject ?predicate ?object . }";
      $where = "WHERE {\n  ?subject ?predicate ?object .\n  FILTER(?subject IN (\n    $uris\n  )) .\n}";
    }

    $query = <<<QUERY
      WITH <$graphUri>
      {$delete}
      INSERT {
      {$triples}
      }
      {$where}
      QUERY;

    RdfSyncConnection::update($query);
  }

  /**
   * Returns a list of concatenated serialized URIs.
   *
   * @param array $entities
   *   The list of entities.
   * @param string $glue
   *   The separator.
   *
   * @return string
   *   List of concatenated serialized URIs.
   */
  protected function getSerializedUris(array $entities, string $glue = ', '): string {
    $rdfSerializer = new Ntriples();

    $uris = [];
    foreach ($entities as $entity) {
      $uris[] = $rdfSerializer->serialiseValue([
        'value' => $entity->getUri(),
        'type' => 'uri',
      ]);
    }

    return implode($glue, $uris);
  }

}
