<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements Vitality index queue service.
 */
readonly class VitalityIndexQueue implements VitalityIndexQueueInterface {

  /**
   * Queue name.
   */
  const QUEUE_NAME = 'eu_oss_vitality_index';

  /**
   * Vitality index queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $queue;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.eu_oss_catalogue')]
    protected LoggerChannelInterface $logger,
    QueueFactory $queueFactory,
  ) {
    $this->queue = $queueFactory->get(self::QUEUE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function addToQueue(int $solutionId): void {
    if ($this->preventAdding()) {
      $this->logger->error('Solution was not added to Vitality Index queue because there is already a lot of not processed solutions.');
      return;
    }

    $this->queue->createQueue();
    $this->queue->createItem($solutionId);
  }

  /**
   * Checks if we want to prevent adding.
   *
   * @return bool
   *   True if we want to prevent.
   */
  public function preventAdding(): bool {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $numberOfNodes = $nodeStorage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'oss_solution')
      ->condition('status', TRUE)
      ->condition('oss_archived', FALSE)
      ->count()
      ->execute();

    return $this->queue->numberOfItems() > $numberOfNodes;
  }

}
