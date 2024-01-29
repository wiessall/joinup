<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
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
   * Synchronizes entities to the RDF backend.
   *
   * @param \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities
   *   The entities for which to update the RDF backend.
   * @param string $method
   *   Method: 'insert', 'update' or 'delete'.
   * @param \Drupal\rdf_sync\Model\SyncMode|null $mode
   *   (optional) The synchronization mode. If passed, will override the stored
   *   synchronization mode.
   */
  public function synchronize(array $entities, string $method, ?SyncMode $mode = NULL): void {
    $mode ??= $this->getSyncMode();

    switch ($mode) {
      case SyncMode::INSTANT:
        $this->doSynchronize($entities, $method);
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
