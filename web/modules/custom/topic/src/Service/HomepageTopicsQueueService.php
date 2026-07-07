<?php

declare(strict_types=1);

namespace Drupal\topic\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entityqueue\EntitySubqueueInterface;
use Drupal\topic\Entity\TopicTermInterface;

/**
 * Provides helpers for the homepage topics entityqueue.
 */
class HomepageTopicsQueueService {

  private const string QUEUE_ID = 'homepage_topics';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the homepage topics subqueue entity.
   */
  public function getQueue(): ?EntitySubqueueInterface {
    return $this->entityTypeManager
      ->getStorage('entity_subqueue')
      ->load(self::QUEUE_ID);
  }

  /**
   * Checks whether a topic term is present in the homepage topics queue.
   *
   * @param \Drupal\topic\Entity\TopicTermInterface $topic
   *   The topic term entity.
   */
  public function isInQueue(TopicTermInterface $topic): bool {
    $subqueue = $this->getQueue();

    if (!$subqueue) {
      return FALSE;
    }

    return $subqueue->hasItem($topic);
  }

}
