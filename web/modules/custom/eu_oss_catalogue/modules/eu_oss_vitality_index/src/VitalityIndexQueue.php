<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index;

use Drupal\Core\Database\Connection;

use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Implements Vitality index queue service.
 */
class VitalityIndexQueue implements VitalityIndexQueueInterface {

  /**
   * Queue name.
   */
  const QUEUE_NAME = 'eu_oss_vitality_index';

  /**
   * Table exists.
   *
   * @var bool|null
   */
  protected bool|null $tableExists = NULL;

  /**
   * Vitality index queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  public function __construct(
    protected Connection $connection,
    QueueFactory $queueFactory
  ) {
    $this->queue = $queueFactory->get(self::QUEUE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function addToQueue(int $solutionId): void {
    if ($this->nodeExistsInQueue($solutionId)) {
      return;
    }

    $this->queue->createQueue();
    $this->queue->createItem($solutionId);
  }

  /**
   * Check if a node exists in the queue.
   *
   * @param int $solutionId
   *   The OSS Solution entity id.
   *
   * @return bool
   *   Whether the solution exists in queue.
   */
  public function nodeExistsInQueue(int $solutionId): bool {
    // The queue table does not exist, and a node was not added to queue.
    if (!$this->queueTableExists()) {
      return FALSE;
    }

    $query = $this->connection->select(DatabaseQueue::TABLE_NAME, 'q');
    $query->condition('name', self::QUEUE_NAME);
    $query->condition('data', serialize($solutionId));
    $query->condition('expire', 0);
    $query->addExpression('COUNT(*)');

    return $query->execute()->fetchField() > 0;
  }

  /**
   * Checks if the queue table exists.
   *
   * @return bool
   *   TRUE if the table exists, otherwise FALSE.
   */
  protected function queueTableExists(): bool {
    if (is_null($this->tableExists)) {
      $this->tableExists = $this->connection->schema()
        ->tableExists(DatabaseQueue::TABLE_NAME);
    }

    return $this->tableExists;
  }

}
