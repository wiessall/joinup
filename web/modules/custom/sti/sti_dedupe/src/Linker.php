<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\Exception\InvariantViolationException;
use Drupal\sti_dedupe\Exception\SurvivorRequiredException;

/**
 * Applies reviewer decisions about pairs of solutions.
 *
 * Solutions are source records: one collection's account of an innovation.
 * They are never merged or deleted here. "Same" is written in two places:
 *
 * - on the solutions: every member of the cluster gets a sameInnovationAs
 *   claim naming every other member (SameAsClaims), always;
 * - on the ATIO innovation, which lists every solution it derives from:
 *
 * - neither side (nor anything already linked to either) is on an
 *   innovation yet: only the decision and the claims are written. When
 *   an innovation later picks up any member of the cluster, the next "same"
 *   decision touching it brings the whole cluster along.
 * - one innovation: every solution in the cluster is added to it.
 * - several innovations: the reviewer picks a survivor; it receives the
 *   others' derived-from entries and every cluster member, and the others
 *   are unpublished and recorded as replaced. Nothing is deleted, so a
 *   curator can reverse it by hand.
 *
 * Every write happens in one transaction and is checked before commit. Note
 * that the transaction covers SQL only: rdf_sync pushes saved entities to
 * the triplestore as they are saved, and a rollback does not retract that.
 * A failed link therefore wants a re-sync of the entities involved.
 */
final class Linker {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly DecisionStore $decisions,
    private readonly CandidateStore $candidates,
    private readonly ReplacementStore $replacements,
    private readonly InnovationLookup $innovations,
    private readonly SameAsClaims $sameAs,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Computes what "same" would do for a pair, without writing anything.
   *
   * The cluster includes the pair itself even though "same" has not been
   * recorded yet, which is what the decision would add.
   */
  public function plan(NodeInterface $a, NodeInterface $b): LinkPlan {
    $this->assertPair($a, $b);
    $cluster = $this->existingSolutions($this->decisions->cluster((int) $a->id(), (int) $b->id()));
    return new LinkPlan($cluster, $this->innovations->derivedFrom($cluster));
  }

  /**
   * Records that two solutions describe the same innovation, and links them.
   *
   * @param \Drupal\node\NodeInterface $a
   *   One solution.
   * @param \Drupal\node\NodeInterface $b
   *   The other solution.
   * @param int|null $survivorNid
   *   The innovation that survives when the cluster spans several. Ignored
   *   when it spans one or none.
   *
   * @throws \Drupal\sti_dedupe\Exception\SurvivorRequiredException
   *   When the cluster spans several innovations and no valid survivor was
   *   given. Nothing is written.
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   *   When the link was written but does not check out. Rolled back.
   */
  public function linkSame(NodeInterface $a, NodeInterface $b, ?int $survivorNid = NULL): LinkResult {
    $this->assertPair($a, $b);

    // Refused before anything is written, the claims on the solutions
    // included: a rollback covers SQL but not a non-SQL entity cache.
    $plan = $this->plan($a, $b);
    if ($plan->needsSurvivor() && !in_array($survivorNid, $plan->innovations, TRUE)) {
      throw new SurvivorRequiredException($plan->innovations);
    }

    $transaction = $this->database->startTransaction();
    try {
      $this->decisions->record((int) $a->id(), (int) $b->id(), DecisionStore::SAME);
      $this->candidates->deletePairsWithin($plan->cluster);
      $this->sameAs->linkCluster($plan->cluster);
      $this->sameAs->verify($plan->cluster);

      if (!$plan->innovations) {
        return new LinkResult($plan->cluster, NULL);
      }

      $survivorId = $plan->needsSurvivor() ? (int) $survivorNid : $plan->innovations[0];
      $losers = array_values(array_diff($plan->innovations, [$survivorId]));

      $added = $this->linkOnto($survivorId, $plan->cluster, $losers);
      foreach ($losers as $loserId) {
        $this->retire($loserId, $survivorId);
      }

      $this->verify($survivorId, $plan->cluster, $losers);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      // Saved entities may have reached a persistent cache (memcache) that
      // the rollback does not touch.
      $this->entityTypeManager->getStorage('node')->resetCache(array_merge($plan->cluster, $plan->innovations));
      throw $e;
    }
    return new LinkResult($plan->cluster, $survivorId, $added, $losers, $refreshed);
  }

  /**
   * Records that two solutions are different innovations.
   *
   * @throws \LogicException
   *   When the pair was already decided "same": undoing a link touches
   *   innovations and is a curator's job, not a button's.
   */
  public function markDifferent(NodeInterface $a, NodeInterface $b): void {
    $this->assertPair($a, $b);
    if ($this->decisions->get((int) $a->id(), (int) $b->id()) === DecisionStore::SAME) {
      throw new \LogicException(sprintf('Solutions %d and %d were already decided to be the same innovation.', $a->id(), $b->id()));
    }
    $this->decisions->record((int) $a->id(), (int) $b->id(), DecisionStore::DIFFERENT);
    $this->candidates->deletePair((int) $a->id(), (int) $b->id());
  }

  /**
   * Adds the cluster and the losers' sources to the survivor.
   *
   * Entries taken over from a loser keep the solution revision the loser
   * pinned. New cluster members are pinned at their current revision.
   *
   * @return int[]
   *   Solution ids newly added.
   */
  private function linkOnto(int $survivorId, array $cluster, array $losers): array {
    $survivor = $this->loadInnovation($survivorId);
    $items = $survivor->get(ContentModel::DERIVED_FROM)->getValue();
    $present = array_map(static fn(array $item): int => (int) $item['target_id'], $items);

    foreach ($losers as $loserId) {
      foreach ($this->loadInnovation($loserId)->get(ContentModel::DERIVED_FROM)->getValue() as $item) {
        if (!in_array((int) $item['target_id'], $present, TRUE)) {
          $items[] = ['target_id' => $item['target_id'], 'target_revision_id' => $item['target_revision_id']];
          $present[] = (int) $item['target_id'];
        }
      }
    }

    $added = [];
    $solutions = $this->entityTypeManager->getStorage('node')->loadMultiple($cluster);
    foreach ($cluster as $nid) {
      if (in_array($nid, $present, TRUE) || !isset($solutions[$nid])) {
        continue;
      }
      $items[] = ['target_id' => $nid, 'target_revision_id' => $solutions[$nid]->getRevisionId()];
      $present[] = $nid;
      $added[] = $nid;
    }

    if (count($items) !== $survivor->get(ContentModel::DERIVED_FROM)->count()) {
      $survivor->set(ContentModel::DERIVED_FROM, $items);
      $survivor->setNewRevision(TRUE);
      $survivor->setRevisionUserId((int) $this->currentUser->id());
      $survivor->setRevisionLogMessage(sprintf('Duplicate review: derived-from now covers solutions %s.', implode(', ', $cluster)));
      $survivor->save();
    }
    return $added;
  }

  /**
   * Unpublishes a replaced innovation and records its survivor.
   */
  private function retire(int $loserId, int $survivorId): void {
    $loser = $this->loadInnovation($loserId);
    $loser->setUnpublished();
    $loser->setNewRevision(TRUE);
    $loser->setRevisionUserId((int) $this->currentUser->id());
    $loser->setRevisionLogMessage(sprintf('Duplicate review: replaced by innovation %d. Unpublished, not deleted.', $survivorId));
    $loser->save();
    $this->replacements->record($loserId, $survivorId);
  }

  /**
   * Checks the outcome against storage, not against the objects just saved.
   *
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   */
  private function verify(int $survivorId, array $cluster, array $losers): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache(array_merge([$survivorId], $losers, $cluster));

    $problems = [];
    $survivor = $storage->load($survivorId);
    $linked = $survivor instanceof NodeInterface
      ? array_map(static fn(array $item): int => (int) $item['target_id'], $survivor->get(ContentModel::DERIVED_FROM)->getValue())
      : [];
    $existing = array_keys($storage->loadMultiple($cluster));
    if ($missing = array_diff($existing, $linked)) {
      $problems[] = sprintf('solutions %s are not on survivor %d', implode(', ', $missing), $survivorId);
    }
    foreach ($losers as $loserId) {
      $loser = $storage->load($loserId);
      if (!$loser instanceof NodeInterface) {
        $problems[] = "replaced innovation $loserId no longer exists";
      }
      elseif ($loser->isPublished()) {
        $problems[] = "replaced innovation $loserId is still published";
      }
      if ($this->replacements->survivorOf($loserId) !== $survivorId) {
        $problems[] = "replacement of $loserId by $survivorId was not recorded";
      }
    }

    if ($problems) {
      $message = 'Duplicate link failed its own check: ' . implode('; ', $problems) . '.';
      $this->logger->error($message);
      throw new InvariantViolationException($message);
    }
  }

  /**
   * Keeps only the ids of solutions that still exist.
   *
   * @return int[]
   *   Existing solution ids, ascending.
   */
  private function existingSolutions(array $nids): array {
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    $ids = [];
    foreach ($nodes as $nid => $node) {
      if ($node instanceof NodeInterface && $node->bundle() === ContentModel::SOLUTION) {
        $ids[] = (int) $nid;
      }
    }
    sort($ids);
    return $ids;
  }

  /**
   * Loads an innovation or fails loudly.
   */
  private function loadInnovation(int $nid): NodeInterface {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== ContentModel::INNOVATION) {
      throw new \UnexpectedValueException("Node $nid is not an ATIO innovation.");
    }
    return $node;
  }

  /**
   * Validates that a and b are two distinct solutions.
   */
  private function assertPair(NodeInterface $a, NodeInterface $b): void {
    if ($a->bundle() !== ContentModel::SOLUTION || $b->bundle() !== ContentModel::SOLUTION) {
      throw new \InvalidArgumentException('Only solutions can be paired.');
    }
    if ((int) $a->id() === (int) $b->id()) {
      throw new \InvalidArgumentException('A solution cannot be paired with itself.');
    }
  }

}
