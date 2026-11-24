<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Records each collection's decisions about pairs of solutions.
 *
 * A decision belongs to the collection that made it: collection A deciding
 * that its record a is the same as b says nothing on behalf of b's
 * collection, which can decide the pair for itself. Within a collection the
 * pair is unordered, so it is stored low nid first. Two outcomes:
 *
 * - same: recorded here, and as a sameInnovationAs claim on the collection's
 *   own record (SameAsClaims). ATIO innovations are not touched.
 * - different: the pair is not offered to that collection again.
 *
 * 'merged' rows are history from the first version of this module and name
 * a deleted node.
 */
final class DecisionStore {

  public const SAME = 'same';
  public const DIFFERENT = 'different';
  public const LEGACY_MERGED = 'merged';

  private const TABLE = 'sti_dedupe_decision';

  public function __construct(
    private readonly Connection $database,
    private readonly AccountProxyInterface $currentUser,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Records a collection's decision about a pair, replacing an earlier one.
   *
   * @throws \InvalidArgumentException
   *   For a pair of one node, or a decision other than SAME or DIFFERENT.
   */
  public function record(int $collection, int $a, int $b, string $decision): void {
    if (!in_array($decision, [self::SAME, self::DIFFERENT], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unknown decision "%s".', $decision));
    }
    if ($a === $b) {
      throw new \InvalidArgumentException('A solution cannot be paired with itself.');
    }
    [$low, $high] = $this->order($a, $b);
    $this->database->merge(self::TABLE)
      ->keys(['collection_nid' => $collection, 'nid_low' => $low, 'nid_high' => $high])
      ->fields([
        'decision' => $decision,
        'uid' => (int) $this->currentUser->id(),
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Returns a collection's decision about a pair, if any.
   */
  public function get(int $collection, int $a, int $b): ?string {
    [$low, $high] = $this->order($a, $b);
    $decision = $this->database->select(self::TABLE, 'd')
      ->fields('d', ['decision'])
      ->condition('collection_nid', $collection)
      ->condition('nid_low', $low)
      ->condition('nid_high', $high)
      ->execute()
      ->fetchField();
    return $decision === FALSE ? NULL : (string) $decision;
  }

  /**
   * Returns every node a collection has decided about with the given one.
   *
   * @return int[]
   *   Node ids, any outcome, in no particular order.
   */
  public function decidedWith(int $collection, int $nid): array {
    $query = $this->database->select(self::TABLE, 'd');
    $query->fields('d', ['nid_low', 'nid_high']);
    $query->condition('collection_nid', $collection);
    $query->condition($query->orConditionGroup()
      ->condition('nid_low', $nid)
      ->condition('nid_high', $nid));

    $others = [];
    foreach ($query->execute() as $row) {
      $others[] = (int) $row->nid_low === $nid ? (int) $row->nid_high : (int) $row->nid_low;
    }
    return $others;
  }

  /**
   * Returns every pair any collection has decided "same", once each.
   *
   * @return list<array{0: int, 1: int}>
   *   [low, high] pairs, ascending.
   */
  public function samePairs(): array {
    $query = $this->database->select(self::TABLE, 'd');
    $query->fields('d', ['nid_low', 'nid_high']);
    $query->condition('decision', self::SAME);
    $query->distinct();
    $query->orderBy('nid_low')->orderBy('nid_high');

    $pairs = [];
    foreach ($query->execute() as $row) {
      $pairs[] = [(int) $row->nid_low, (int) $row->nid_high];
    }
    return $pairs;
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
