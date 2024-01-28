<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\rdf_sync\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\Model\SyncMode;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Synchronizes the RDF backend.
 */
class RdfSyncSynchronizer {

  use RdfSyncSynchronizerTrait;

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state variables service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory service.
   */
  public function __construct(
    protected StateInterface $state,
    protected SerializerInterface $serializer,
    protected QueueFactory $queueFactory,
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
    $this->handleSynchronization($entities, 'insert');
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
    $this->handleSynchronization($entities, 'update');
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
    $this->handleSynchronization($entities, 'delete');
  }

  /**
   * Decides how to handle the synchronization.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities
   *   The entities for which to update the RDF backend.
   * @param string $method
   *   Method: 'insert', 'update' or 'delete'.
   */
  protected function handleSynchronization(array $entities, string $method): void {
    switch ($this->getSyncMode()) {
      case SyncMode::INSTANT:
        $this->synchronize($entities, $method);
        break;

      case SyncMode::DELAYED:
        $queue = $this->queueFactory->get('rdf_sync_synchronizer');
        $queue->createQueue();

        // Limit the number of entities to 20 per queue item.
        foreach (array_chunk($entities, 20) as $entitiesToQueue) {
          $idsByEntityType = [];
          /** @var \Drupal\rdf_sync\Entity\RdfSyncEntityInterface $entity */
          foreach ($entitiesToQueue as $entity) {
            $idsByEntityType[$entity->getEntityTypeId()][] = $entity->id();
          }
          $queue->createItem([
            'idsByEntityType' => $idsByEntityType,
            'method' => $method,
          ]);
        }
        break;

      case SyncMode::NONE:
        // Nothing to do. The synchronization will be handled manually.
    }
  }

}
