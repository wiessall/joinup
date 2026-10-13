<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Records which ATIO innovation replaced which.
 *
 * When a "same" decision joins two clusters that each already had an ATIO
 * innovation, the reviewer picks a survivor; the other innovation is
 * unpublished, never deleted, and recorded here. The record is what keeps the
 * replaced innovation's URI resolving (to the survivor), and it is the audit
 * trail for an operation a curator may want to undo by hand.
 */
final class ReplacementStore {

  private const TABLE = 'sti_dedupe_replacement';

  /**
   * Longest replacement chain followed before assuming a cycle.
   */
  private const MAX_CHAIN = 50;

  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Records that one innovation was replaced by another.
   *
   * @throws \InvalidArgumentException
   *   When an innovation would replace itself.
   */
  public function record(int $replaced, int $survivor): void {
    if ($replaced === $survivor) {
      throw new \InvalidArgumentException('An innovation cannot replace itself.');
    }
    $this->database->merge(self::TABLE)
      ->keys(['replaced_nid' => $replaced])
      ->fields([
        'survivor_nid' => $survivor,
        'uid' => (int) $this->currentUser->id(),
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Returns the current survivor of a replaced innovation.
   *
   * Follows chains (A replaced by B, B later replaced by C gives C).
   *
   * @return int|null
   *   The final survivor, or NULL when the innovation was never replaced.
   *
   * @throws \RuntimeException
   *   When the chain loops, which means the table is corrupt.
   */
  public function survivorOf(int $nid): ?int {
    $current = $nid;
    $seen = [$nid => TRUE];
    for ($i = 0; $i < self::MAX_CHAIN; $i++) {
      $next = $this->database->select(self::TABLE, 'r')
        ->fields('r', ['survivor_nid'])
        ->condition('replaced_nid', $current)
        ->execute()
        ->fetchField();
      if ($next === FALSE) {
        return $current === $nid ? NULL : $current;
      }
      $current = (int) $next;
      if (isset($seen[$current])) {
        throw new \RuntimeException(sprintf('Replacement chain starting at node %d loops.', $nid));
      }
      $seen[$current] = TRUE;
    }
    throw new \RuntimeException(sprintf('Replacement chain starting at node %d is longer than %d.', $nid, self::MAX_CHAIN));
  }

  /**
   * Returns which of the given innovations have been replaced.
   *
   * @param int[] $nids
   *   Innovation node ids.
   *
   * @return int[]
   *   The subset that has a replacement record.
   */
  public function replacedAmong(array $nids): array {
    if (!$nids) {
      return [];
    }
    return array_map('intval', $this->database->select(self::TABLE, 'r')
      ->fields('r', ['replaced_nid'])
      ->condition('replaced_nid', $nids, 'IN')
      ->execute()
      ->fetchCol());
  }

}
