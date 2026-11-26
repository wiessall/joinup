<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\Exception\InvariantViolationException;

/**
 * Applies duplicate review decisions.
 *
 * Two roles, kept apart:
 *
 * - A collection decides about its own records (decideSame(),
 *   decideDifferent()). "Same" writes the decision and one sameInnovationAs
 *   claim on the collection's record. A site-level curator's "same" also
 *   writes the reverse claim and decision, attributed to the other record's
 *   collection, as if that collection had decided too. No ATIO
 *   innovation is written: atio_derived_from says what an innovation was
 *   derived from, and that has not changed. The claim is read when the
 *   innovation is next updated (SameAsClaims::linkedTo()).
 * - An ATIO curator reconciles innovations when "same" records sit on
 *   different ones (pairsToReconcile(), mergeInnovations()).
 *
 * Writes happen in one transaction and are checked before commit. The
 * transaction covers SQL only: rdf_sync pushes saved entities to the
 * triplestore as they are saved, and a rollback does not retract that. A
 * failed write therefore wants a re-sync of the entities involved.
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
   * Records that a collection's record is the same innovation as another.
   *
   * @param bool $bothDirections
   *   Also record it for the object's collection, with the reverse claim on
   *   the object. For site-level curators; the caller checks the permission.
   *
   * @throws \InvalidArgumentException
   *   When the subject is not in the collection, or the pair is invalid.
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   *   When the claim was written but does not check out. Rolled back.
   */
  public function decideSame(NodeInterface $collection, NodeInterface $subject, NodeInterface $object, bool $bothDirections = FALSE): void {
    $this->assertDecidable($collection, $subject, $object);
    $transaction = $this->database->startTransaction();
    try {
      $this->decisions->record((int) $collection->id(), (int) $subject->id(), (int) $object->id(), DecisionStore::SAME);
      $this->candidates->deletePair((int) $subject->id(), (int) $object->id());
      $this->sameAs->claim($subject, $object);
      if ($bothDirections) {
        // claim() throws when the object has no collection; the rollback
        // then undoes the subject's side too.
        $this->sameAs->claim($object, $subject);
        $this->decisions->record((int) $object->get(ContentModel::AUDIENCE)->target_id, (int) $object->id(), (int) $subject->id(), DecisionStore::SAME);
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      // Saved entities may have reached a persistent cache (memcache) that
      // the rollback does not touch.
      $this->entityTypeManager->getStorage('node')->resetCache([$subject->id(), $object->id()]);
      throw $e;
    }
  }

  /**
   * Records that a collection's record is not the same as another.
   *
   * @throws \LogicException
   *   When the collection already decided "same": its claim stands until
   *   someone removes it deliberately.
   */
  public function decideDifferent(NodeInterface $collection, NodeInterface $subject, NodeInterface $object): void {
    $this->assertDecidable($collection, $subject, $object);
    $collectionId = (int) $collection->id();
    if ($this->decisions->get($collectionId, (int) $subject->id(), (int) $object->id()) === DecisionStore::SAME) {
      throw new \LogicException(sprintf('%s already decided that solutions %d and %d are the same innovation.', $collection->label(), $subject->id(), $object->id()));
    }
    $this->decisions->record($collectionId, (int) $subject->id(), (int) $object->id(), DecisionStore::DIFFERENT);
    $this->candidates->deletePair((int) $subject->id(), (int) $object->id());
  }

  /**
   * Lists "same" pairs whose records sit on different ATIO innovations.
   *
   * Computed from the decisions on every call; nothing is stored.
   *
   * @return list<array{solutions: array{0: int, 1: int}, innovations: int[]}>
   *   Each pair with the live innovations of both sides, ascending.
   */
  public function pairsToReconcile(): array {
    $out = [];
    foreach ($this->decisions->samePairs() as [$low, $high]) {
      $lowSide = $this->innovations->derivedFrom([$low]);
      $highSide = $this->innovations->derivedFrom([$high]);
      // Nothing to reconcile when a side is on no innovation, or both are on
      // the same ones.
      if (!$lowSide || !$highSide || (!array_diff($lowSide, $highSide) && !array_diff($highSide, $lowSide))) {
        continue;
      }
      $all = array_values(array_unique(array_merge($lowSide, $highSide)));
      sort($all);
      $out[] = ['solutions' => [$low, $high], 'innovations' => $all];
    }
    return $out;
  }

  /**
   * Folds innovations into a survivor. ATIO curators only.
   *
   * The survivor takes over the losers' derived-from entries, pinned at the
   * revisions the losers used; nothing else is added. The losers are
   * unpublished, not deleted, and recorded as replaced, so their URIs answer
   * 301 to the survivor.
   *
   * @param int $survivorId
   *   The innovation that remains.
   * @param int[] $loserIds
   *   The innovations folded into it.
   *
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   *   When the merge was written but does not check out. Rolled back.
   */
  public function mergeInnovations(int $survivorId, array $loserIds): void {
    $loserIds = array_values(array_diff(array_map('intval', $loserIds), [$survivorId]));
    if (!$loserIds) {
      return;
    }
    $transaction = $this->database->startTransaction();
    try {
      $this->takeOverSources($survivorId, $loserIds);
      foreach ($loserIds as $loserId) {
        $this->retire($loserId, $survivorId);
      }
      $this->verifyMerge($survivorId, $loserIds);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      $this->entityTypeManager->getStorage('node')->resetCache(array_merge([$survivorId], $loserIds));
      throw $e;
    }
  }

  /**
   * Adds the losers' derived-from entries the survivor lacks.
   */
  private function takeOverSources(int $survivorId, array $loserIds): void {
    $survivor = $this->loadInnovation($survivorId);
    $items = $survivor->get(ContentModel::DERIVED_FROM)->getValue();
    $present = array_map(static fn(array $item): int => (int) $item['target_id'], $items);
    $before = count($items);

    foreach ($loserIds as $loserId) {
      foreach ($this->loadInnovation($loserId)->get(ContentModel::DERIVED_FROM)->getValue() as $item) {
        if (!in_array((int) $item['target_id'], $present, TRUE)) {
          $items[] = ['target_id' => $item['target_id'], 'target_revision_id' => $item['target_revision_id']];
          $present[] = (int) $item['target_id'];
        }
      }
    }

    if (count($items) !== $before) {
      $survivor->set(ContentModel::DERIVED_FROM, $items);
      $survivor->setNewRevision(TRUE);
      $survivor->setRevisionUserId((int) $this->currentUser->id());
      $survivor->setRevisionLogMessage(sprintf('Duplicate review: took over the sources of innovation(s) %s.', implode(', ', $loserIds)));
      $survivor->save();
    }
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
   * Checks a merge against storage, not against the objects just saved.
   *
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   */
  private function verifyMerge(int $survivorId, array $loserIds): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache(array_merge([$survivorId], $loserIds));
    $sourcesOf = static fn(?NodeInterface $node): array => $node
      ? array_map(static fn(array $item): int => (int) $item['target_id'], $node->get(ContentModel::DERIVED_FROM)->getValue())
      : [];

    $problems = [];
    $survivor = $storage->load($survivorId);
    $kept = $sourcesOf($survivor instanceof NodeInterface ? $survivor : NULL);
    foreach ($loserIds as $loserId) {
      $loser = $storage->load($loserId);
      if (!$loser instanceof NodeInterface) {
        $problems[] = "replaced innovation $loserId no longer exists";
        continue;
      }
      if ($loser->isPublished()) {
        $problems[] = "replaced innovation $loserId is still published";
      }
      if ($missing = array_diff($sourcesOf($loser), $kept)) {
        $problems[] = sprintf('sources %s of %d are not on survivor %d', implode(', ', $missing), $loserId, $survivorId);
      }
      if ($this->replacements->survivorOf($loserId) !== $survivorId) {
        $problems[] = "replacement of $loserId by $survivorId was not recorded";
      }
    }

    if ($problems) {
      $message = 'Innovation merge failed its own check: ' . implode('; ', $problems) . '.';
      $this->logger->error($message);
      throw new InvariantViolationException($message);
    }
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
   * Validates a pair of distinct solutions, the subject in the collection.
   *
   * The access check stops other collections' facilitators at the route;
   * this stops a hand-edited URL from writing on another collection's
   * record, which would otherwise succeed and look valid.
   */
  private function assertDecidable(NodeInterface $collection, NodeInterface $subject, NodeInterface $object): void {
    if ($subject->bundle() !== ContentModel::SOLUTION || $object->bundle() !== ContentModel::SOLUTION) {
      throw new \InvalidArgumentException('Only solutions can be paired.');
    }
    if ((int) $subject->id() === (int) $object->id()) {
      throw new \InvalidArgumentException('A solution cannot be paired with itself.');
    }
    if (!$subject->hasField(ContentModel::AUDIENCE) || (int) $subject->get(ContentModel::AUDIENCE)->target_id !== (int) $collection->id()) {
      throw new \InvalidArgumentException(sprintf('Solution %d is not in collection %d.', $subject->id(), $collection->id()));
    }
  }

}
