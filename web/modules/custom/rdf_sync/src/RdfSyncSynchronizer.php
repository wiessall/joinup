<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\Core\State\StateInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Model\SyncMode;
use EasyRdf\Serialiser\Ntriples;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Synchronizes the RDF backend.
 */
class RdfSyncSynchronizer {

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *    The state variables service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   */
  public function __construct(
    protected StateInterface $state,
    protected SerializerInterface $serializer,
  ) {}

  /**
   * Returns the RDF synchronization mode.
   *
   * @return \Drupal\rdf_sync\Model\SyncMode
   *   The RDF synchronization mode. The default value is SyncMode::DELAYED.
   */
  public function getSyncMode(): SyncMode {
    $mode = $this->state->get('rdf_sync.sync_mode', SyncMode::DELAYED->value);
    return SyncMode::from($mode);
  }

  /**
   * Sets the RDF synchronization mode.
   *
   * @param \Drupal\rdf_sync\Model\SyncMode $mode
   *   The RDF synchronization mode to be set.
   *
   * @return $this
   */
  public function setSyncMode(SyncMode $mode): self {
    $this->state->set('rdf_sync.sync_mode', $mode->value);
    return $this;
  }

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
