<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\sti_dedupe\Plugin\QueueWorker\ConflictRefreshWorker;

/**
 * Queues claim conflict refreshes.
 *
 * A full refresh is requested whenever a curator changes a property or value
 * mapping. Curators map terms in batches, so the request is collapsed: while
 * one full refresh is waiting, further requests add nothing.
 */
final class ConflictQueue {

  /**
   * State key set while a full refresh is queued and not yet fanned out.
   */
  public const FULL_PENDING = 'sti_dedupe.full_refresh_pending';

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly StateInterface $state,
  ) {}

  /**
   * Queues a refresh of the given innovations.
   *
   * @param int[] $nids
   *   Innovation node ids; nothing is queued for an empty list.
   */
  public function queue(array $nids): void {
    if ($nids) {
      $this->queueFactory->get(ConflictRefreshWorker::QUEUE)->createItem(['nids' => array_values(array_map('intval', $nids))]);
    }
  }

  /**
   * Queues a refresh of every innovation, once.
   */
  public function queueAll(): void {
    if ($this->state->get(self::FULL_PENDING)) {
      return;
    }
    $this->state->set(self::FULL_PENDING, TRUE);
    $this->queueFactory->get(ConflictRefreshWorker::QUEUE)->createItem(['all' => TRUE]);
  }

  /**
   * Marks the pending full refresh as fanned out.
   *
   * Called by the worker before it queues the per-innovation items, so that
   * a mapping change made while those items run requests a new full pass.
   */
  public function fullRefreshStarted(): void {
    $this->state->delete(self::FULL_PENDING);
  }

}
