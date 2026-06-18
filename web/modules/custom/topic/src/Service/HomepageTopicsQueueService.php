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

  /**
   * Lazy-loaded cache of term IDs present in the queue.
   *
   * @var string[]|null
   */
  private ?array $termIds = NULL;

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
    return in_array($termId, $this->getTermIds());
  }

  /**
   * Returns the term IDs currently in the queue.
   *
   * @return string[]
   *   Term IDs.
   */
  private function getTermIds(): array {
    if ($this->termIds !== NULL) {
      return $this->termIds;
    }

    $this->termIds = [];
    $subqueue = $this->getQueue();

    if ($subqueue) {
      foreach ($subqueue->get('items') as $item) {
        $this->termIds[] = $item->target_id;
      }
    }

    return $this->termIds;
  }

}
