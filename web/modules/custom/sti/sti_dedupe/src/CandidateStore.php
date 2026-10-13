<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Stores precomputed duplicate candidates.
 *
 * Scanning is a batch step run after a migration, not work done per page
 * request: at the target scale (200 collections of ~1400 solutions) a search
 * per row per page load would not be acceptable.
 */
final class CandidateStore {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Replaces the stored candidates for one solution.
   *
   * @param int $sourceNid
   *   The solution the candidates were computed for.
   * @param int $collectionNid
   *   The solution's collection, denormalised for cheap listing.
   * @param \Drupal\sti_dedupe\CandidateMatch[] $matches
   *   The matches to store.
   */
  public function replaceForSource(int $sourceNid, int $collectionNid, array $matches): void {
    $this->database->delete('sti_dedupe_candidate')
      ->condition('source_nid', $sourceNid)
      ->execute();

    if (!$matches) {
      return;
    }

    $insert = $this->database->insert('sti_dedupe_candidate')
      ->fields(['source_nid', 'candidate_nid', 'collection_nid', 'score', 'reasons', 'computed']);
    foreach ($matches as $match) {
      $insert->values([
        'source_nid' => $sourceNid,
        'candidate_nid' => (int) $match->node->id(),
        'collection_nid' => $collectionNid,
        'score' => (int) round($match->score * 1000),
        'reasons' => mb_substr($match->reasonsAsString(), 0, 255),
        'computed' => $this->time->getRequestTime(),
      ]);
    }
    $insert->execute();
  }

  /**
   * Counts solutions in a collection that still have undecided candidates.
   */
  public function countSourcesForCollection(int $collectionNid): int {
    $query = $this->database->select('sti_dedupe_candidate', 'c');
    $query->addExpression('COUNT(DISTINCT c.source_nid)', 'total');
    $query->condition('c.collection_nid', $collectionNid);
    return (int) $query->execute()->fetchField();
  }

  /**
   * Returns one page of solutions with candidates, best score first.
   *
   * @return array<int, array{count: int, best_nid: int, best_score: float, reasons: string}>
   *   Keyed by source node id.
   */
  public function pageForCollection(int $collectionNid, int $limit, int $offset): array {
    $query = $this->database->select('sti_dedupe_candidate', 'c');
    $query->fields('c', ['source_nid']);
    $query->addExpression('MAX(c.score)', 'best');
    $query->condition('c.collection_nid', $collectionNid);
    $query->groupBy('c.source_nid');
    $query->orderBy('best', 'DESC');
    $query->range($offset, $limit);
    $sourceNids = $query->execute()->fetchCol();

    if (!$sourceNids) {
      return [];
    }

    $rows = $this->database->select('sti_dedupe_candidate', 'c')
      ->fields('c', ['source_nid', 'candidate_nid', 'score', 'reasons'])
      ->condition('c.source_nid', $sourceNids, 'IN')
      ->orderBy('c.score', 'DESC')
      ->execute();

    $out = [];
    foreach ($rows as $row) {
      $source = (int) $row->source_nid;
      if (!isset($out[$source])) {
        $out[$source] = [
          'count' => 0,
          'best_nid' => (int) $row->candidate_nid,
          'best_score' => ((int) $row->score) / 1000,
          'reasons' => (string) $row->reasons,
        ];
      }
      $out[$source]['count']++;
    }

    // Preserve the ordering established by the grouped query.
    $ordered = [];
    foreach ($sourceNids as $nid) {
      if (isset($out[(int) $nid])) {
        $ordered[(int) $nid] = $out[(int) $nid];
      }
    }
    return $ordered;
  }

  /**
   * Returns all stored candidates for one solution, best first.
   *
   * @return array<int, array{score: float, reasons: string}>
   *   Keyed by candidate node id.
   */
  public function forSource(int $sourceNid): array {
    $rows = $this->database->select('sti_dedupe_candidate', 'c')
      ->fields('c', ['candidate_nid', 'score', 'reasons'])
      ->condition('c.source_nid', $sourceNid)
      ->orderBy('c.score', 'DESC')
      ->execute();

    $out = [];
    foreach ($rows as $row) {
      $out[(int) $row->candidate_nid] = [
        'score' => ((int) $row->score) / 1000,
        'reasons' => (string) $row->reasons,
      ];
    }
    return $out;
  }

  /**
   * Removes a single pair, in both directions.
   */
  public function deletePair(int $a, int $b): void {
    $this->database->delete('sti_dedupe_candidate')
      ->condition('source_nid', $a)
      ->condition('candidate_nid', $b)
      ->execute();
    $this->database->delete('sti_dedupe_candidate')
      ->condition('source_nid', $b)
      ->condition('candidate_nid', $a)
      ->execute();
  }

  /**
   * Removes every candidate pair whose two ends are both in a set.
   *
   * Used after a "same" decision: within a cluster, every pair is decided by
   * transitivity, so none of them should be offered again.
   *
   * @param int[] $nids
   *   The cluster.
   */
  public function deletePairsWithin(array $nids): void {
    if (count($nids) < 2) {
      return;
    }
    $this->database->delete('sti_dedupe_candidate')
      ->condition('source_nid', $nids, 'IN')
      ->condition('candidate_nid', $nids, 'IN')
      ->execute();
  }

  /**
   * Removes every candidate row mentioning a node, after it is deleted.
   */
  public function deleteForNode(int $nid): void {
    $this->database->delete('sti_dedupe_candidate')
      ->condition('source_nid', $nid)
      ->execute();
    $this->database->delete('sti_dedupe_candidate')
      ->condition('candidate_nid', $nid)
      ->execute();
  }

}
