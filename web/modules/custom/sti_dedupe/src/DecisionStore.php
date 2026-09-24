<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Records and reads reviewer decisions about candidate pairs.
 *
 * A decision is symmetric: deciding that A and B are different must also
 * suppress the pair (B, A). Pairs are therefore stored low-nid first.
 *
 * Recording "different" is not cosmetic — without it the same pair is offered
 * again on every re-import and reviewers redo the work. See ADR #12.
 */
final class DecisionStore {

  public const MERGED = 'merged';
  public const SAME_SEPARATE = 'same_separate';
  public const DIFFERENT = 'different';

  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Records a decision about a pair, replacing any earlier one.
   */
  public function record(int $a, int $b, string $decision): void {
    [$low, $high] = $this->order($a, $b);
    $this->database->merge('sti_dedupe_decision')
      ->keys([
        'nid_low' => $low,
        'nid_high' => $high,
      ])
      ->fields([
        'decision' => $decision,
        'uid' => (int) $this->currentUser->id(),
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Whether this pair has already been decided.
   */
  public function isDecided(int $a, int $b): bool {
    [$low, $high] = $this->order($a, $b);
    return (bool) $this->database->select('sti_dedupe_decision', 'd')
      ->fields('d', ['id'])
      ->condition('nid_low', $low)
      ->condition('nid_high', $high)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Returns the node ids already decided against for one node.
   *
   * @return int[]
   *   Node ids, in no particular order.
   */
  public function decidedAgainst(int $nid): array {
    $query = $this->database->select('sti_dedupe_decision', 'd');
    $query->fields('d', ['nid_low', 'nid_high']);
    $group = $query->orConditionGroup()
      ->condition('nid_low', $nid)
      ->condition('nid_high', $nid);
    $query->condition($group);

    $others = [];
    foreach ($query->execute() as $row) {
      $others[] = (int) $row->nid_low === $nid ? (int) $row->nid_high : (int) $row->nid_low;
    }
    return $others;
  }

  /**
   * Removes every decision that mentions a node, after it is deleted.
   */
  public function deleteForNode(int $nid): void {
    $query = $this->database->delete('sti_dedupe_decision');
    $group = $query->orConditionGroup()
      ->condition('nid_low', $nid)
      ->condition('nid_high', $nid);
    $query->condition($group)->execute();
  }

  /**
   * Normalises a pair to (low, high).
   *
   * @return int[]
   *   The two ids, lowest first.
   */
  private function order(int $a, int $b): array {
    return $a <= $b ? [$a, $b] : [$b, $a];
  }

}
