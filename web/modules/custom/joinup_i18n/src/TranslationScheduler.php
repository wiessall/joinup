<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Builds batch operations for scheduling translation of a collection hierarchy.
 */
class TranslationScheduler {

  use AutowireTrait;

  const string STATE_KEY = 'joinup_i18n.scheduled_collections';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly StateInterface $state,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Builds batch operations for a collection and all its descendant content.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The collection node.
   *
   * @return array
   *   An array of [callable, args] pairs for use with batch_set().
   */
  public function buildBatchOperations(CollectionInterface $collection): array {
    $operations = [];
    $operations[] = ['\tmgmt_content_create_continuous_job_items', [$collection]];

    $node_storage = $this->entityTypeManager->getStorage('node');

    foreach ($collection->getGroupContentIds()['node'] ?? [] as $ids) {
      foreach ($node_storage->loadMultiple($ids) as $entity) {
        assert($entity instanceof NodeInterface);
        if (!$entity->isPublished()) {
          continue;
        }
        if ($entity instanceof ArchivableEntityInterface && $entity->isArchived()) {
          continue;
        }

        $operations[] = ['\tmgmt_content_create_continuous_job_items', [$entity]];
      }
    }

    return $operations;
  }

  /**
   * Records the current time as the send timestamp for a collection.
   *
   * @param string $collectionId
   *   The collection node ID.
   */
  public function markAsSent(string $collectionId): void {
    $sent = $this->state->get(self::STATE_KEY, []);
    $sent[$collectionId] = $this->time->getRequestTime();
    $this->state->set(self::STATE_KEY, $sent);
  }

  /**
   * Returns the timestamp when a collection was last scheduled for translation.
   *
   * @param string $collectionId
   *   The collection node ID.
   *
   * @return int|null
   *   Unix timestamp, or null if never sent.
   */
  public function getSentTimestamp(string $collectionId): ?int {
    $sent = $this->state->get(self::STATE_KEY, []);
    return $sent[$collectionId] ?? NULL;
  }

}