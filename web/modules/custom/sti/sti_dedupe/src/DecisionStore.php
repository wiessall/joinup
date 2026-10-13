<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Records reviewer decisions about pairs of solutions.
 *
 * A decision is symmetric, so pairs are stored low nid first. Two outcomes:
 *
 * - same: both solutions describe one real-world innovation. Solutions are
 *   source records and are never merged or deleted; "same" is a link, and
 *   links are transitive in effect: if A~B and B~C were decided, A, B and C
 *   form one cluster and end up on one ATIO innovation.
 * - different: the pair is never offered again.
 *
 * 'merged' and 'same_separate' are values written by the first version of
 * this module. sti_dedupe_update_11001() turns 'same_separate' into 'same';
 * 'merged' rows name a deleted node and are kept as the audit trail.
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
   * Records a decision about a pair, replacing any earlier one.
   *
   * @throws \InvalidArgumentException
   *   For a pair of one node, or a decision other than SAME or DIFFERENT.
   */
  public function record(int $a, int $b, string $decision): void {
    if (!in_array($decision, [self::SAME, self::DIFFERENT], TRUE)) {
      throw new \InvalidArgumentException(sprintf('Unknown decision "%s".', $decision));
    }
    if ($a === $b) {
      throw new \InvalidArgumentException('A solution cannot be paired with itself.');
    }
    [$low, $high] = $this->order($a, $b);
    $this->database->merge(self::TABLE)
      ->keys(['nid_low' => $low, 'nid_high' => $high])
      ->fields([
        'decision' => $decision,
        'uid' => (int) $this->currentUser->id(),
        'created' => $this->time->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Returns the decision recorded for a pair, if any.
   */
  public function get(int $a, int $b): ?string {
    [$low, $high] = $this->order($a, $b);
    $decision = $this->database->select(self::TABLE, 'd')
      ->fields('d', ['decision'])
      ->condition('nid_low', $low)
      ->condition('nid_high', $high)
      ->execute()
      ->fetchField();
    return $decision === FALSE ? NULL : (string) $decision;
  }

  /**
   * Returns every node a decision pairs with the given one, any outcome.
   *
   * @return int[]
   *   Node ids, in no particular order.
   */
  public function decidedWith(int $nid): array {
    $query = $this->database->select(self::TABLE, 'd');
    $query->fields('d', ['nid_low', 'nid_high']);
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
   * Returns the transitive closure of "same" around the given nodes.
   *
   * Legacy 'merged' rows are followed as well: they record that two records
   * were judged the same, even though one of them no longer exists. Callers
   * filter out ids that do not load.
   *
   * @param int ...$nids
   *   One or more seed node ids.
   *
   * @return int[]
   *   The seeds and everything linked to them, sorted ascending.
   */
  public function cluster(int ...$nids): array {
    $members = array_fill_keys($nids, TRUE);
    $frontier = $nids;
    while ($frontier) {
      $query = $this->database->select(self::TABLE, 'd');
      $query->fields('d', ['nid_low', 'nid_high']);
      $query->condition('decision', [self::SAME, self::LEGACY_MERGED], 'IN');
      $query->condition($query->orConditionGroup()
        ->condition('nid_low', $frontier, 'IN')
        ->condition('nid_high', $frontier, 'IN'));

      $next = [];
      foreach ($query->execute() as $row) {
        foreach ([(int) $row->nid_low, (int) $row->nid_high] as $nid) {
          if (!isset($members[$nid])) {
            $members[$nid] = TRUE;
            $next[] = $nid;
          }
        }
      }
      $frontier = $next;
    }

    $out = array_keys($members);
    sort($out);
    return $out;
  }

  /**
   * Returns every "same" cluster with at least two members.
   *
   * Follows legacy 'merged' rows like cluster() does; callers filter out ids
   * that no longer load.
   *
   * @return list<int[]>
   *   Clusters, each sorted ascending, ordered by their lowest id.
   */
  public function sameClusters(): array {
    $parent = [];
    $find = static function (int $nid) use (&$parent): int {
      while ($parent[$nid] !== $nid) {
        $parent[$nid] = $parent[$parent[$nid]];
        $nid = $parent[$nid];
      }
      return $nid;
    };

    $rows = $this->database->select(self::TABLE, 'd')
      ->fields('d', ['nid_low', 'nid_high'])
      ->condition('decision', [self::SAME, self::LEGACY_MERGED], 'IN')
      ->execute();
    foreach ($rows as $row) {
      $low = (int) $row->nid_low;
      $high = (int) $row->nid_high;
      $parent[$low] ??= $low;
      $parent[$high] ??= $high;
      $rootLow = $find($low);
      $rootHigh = $find($high);
      if ($rootLow !== $rootHigh) {
        $parent[max($rootLow, $rootHigh)] = min($rootLow, $rootHigh);
      }
    }

    $clusters = [];
    foreach (array_keys($parent) as $nid) {
      $clusters[$find($nid)][] = $nid;
    }
    ksort($clusters);
    return array_values(array_map(static function (array $members): array {
      sort($members);
      return $members;
    }, $clusters));
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
