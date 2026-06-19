<?php

declare(strict_types=1);

namespace Drupal\topic\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entityqueue\EntitySubqueueInterface;

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
   * @param string|int $termId
   *   The taxonomy term ID.
   */
  public function isInQueue(string|int $termId): bool {
    $subqueue = $this->getQueue();

    if (!$subqueue) {
      return FALSE;
    }

    foreach ($subqueue->get('items') as $item) {
      if ($item->target_id == $termId) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
