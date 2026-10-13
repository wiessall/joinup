<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\Exception\InvariantViolationException;

/**
 * Keeps an innovation's claim resolutions in step with its sources' claims.
 *
 * One claim_resolution paragraph per exclusive ATIO property whose sources
 * disagree, holding one claim_verdict per distinct claimed value. Rules:
 *
 * - A newly conflicting property gets a pending resolution, every verdict
 *   'unreviewed'.
 * - A pending resolution nobody has touched follows the claims: values are
 *   added and removed, and it is dropped when the sources come to agree.
 * - A curator's ruling is never discarded. When the claims it ruled on
 *   change, verdicts for values no longer claimed are kept but flagged
 *   not current, new values are added unreviewed, and the resolution
 *   becomes 'stale' until a curator rules again.
 *
 * Verdicts identify claims by collection + property + value (ClaimTuple),
 * so a re-import that recreates identical claim paragraphs changes nothing.
 *
 * The innovation is saved without a new revision: this is bookkeeping, not
 * an editorial change. A curator's ruling (ResolutionWriter) does create one.
 */
final class ResolutionReconciler {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly ConflictDetector $detector,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Brings one innovation's resolutions up to date.
   *
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   *   When the saved state does not match the claims. Rolled back.
   * @throws \LogicException
   *   When the innovation has no resolutions field, i.e. this module's
   *   config is not installed.
   */
  public function refresh(NodeInterface $innovation): RefreshResult {
    if (!$innovation->hasField(ContentModel::RESOLUTIONS)) {
      throw new \LogicException(sprintf('Bundle %s has no %s field; sti_dedupe config is not installed.', $innovation->bundle(), ContentModel::RESOLUTIONS));
    }

    $groups = $this->detector->groups($innovation);
    $created = $updated = $staled = $removed = 0;
    $kept = [];
    $seen = [];
    $duplicates = [];

    foreach ($innovation->get(ContentModel::RESOLUTIONS)->referencedEntities() as $resolution) {
      if (!$resolution instanceof ParagraphInterface) {
        continue;
      }
      $property = $this->property($resolution);
      if (isset($seen[$property])) {
        // Two resolutions for one property should be impossible. Keep both
        // untouched rather than guess which ruling is the real one.
        $duplicates[$property] = TRUE;
        $kept[] = $resolution;
        continue;
      }
      $seen[$property] = TRUE;
      $group = $groups[$property] ?? NULL;
      $tuples = $group?->tuples ?? [];

      if (!$this->hasCuratorInput($resolution)) {
        if ($group === NULL || !$group->isConflicting()) {
          $removed++;
          continue;
        }
        if ($this->syncUnreviewed($resolution, $tuples)) {
          $updated++;
        }
        $kept[] = $resolution;
        continue;
      }

      if ($this->syncReviewed($resolution, $tuples)) {
        $staled++;
      }
      $kept[] = $resolution;
    }

    foreach ($groups as $property => $group) {
      if ($group->isConflicting() && !isset($seen[$property])) {
        $kept[] = $this->newResolution($property, $group->tuples);
        $created++;
      }
    }

    foreach (array_keys($duplicates) as $property) {
      $this->logger->error('Innovation @nid has more than one claim resolution for @property; left untouched for a curator.', [
        '@nid' => $innovation->id(),
        '@property' => $property,
      ]);
    }

    $result = new RefreshResult($created, $updated, $staled, $removed);
    if (!$result->changed()) {
      return $result;
    }

    $transaction = $this->database->startTransaction();
    try {
      $innovation->set(ContentModel::RESOLUTIONS, $kept);
      $innovation->setNewRevision(FALSE);
      $innovation->save();
      $this->verify((int) $innovation->id(), $groups, array_keys($duplicates));
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    return $result;
  }

  /**
   * Makes an untouched resolution list exactly the current claims.
   *
   * @return bool
   *   Whether anything changed.
   */
  private function syncUnreviewed(ParagraphInterface $resolution, array $tuples): bool {
    $verdicts = $this->verdicts($resolution);
    if (array_keys($verdicts) == array_keys($tuples) && $this->allCurrent($verdicts)) {
      return FALSE;
    }
    $items = [];
    foreach ($tuples as $key => $tuple) {
      $items[] = $verdicts[$key] ?? $this->newVerdict($tuple);
      if (isset($verdicts[$key])) {
        $this->setCurrent($verdicts[$key], TRUE);
      }
    }
    $resolution->set(ContentModel::VERDICTS, $items);
    $resolution->set(ContentModel::RESOLUTION_STATUS, ContentModel::STATUS_PENDING);
    $resolution->setNeedsSave(TRUE);
    return TRUE;
  }

  /**
   * Flags changes against a curator's ruling, keeping every verdict.
   *
   * @return bool
   *   Whether the claims changed since the ruling.
   */
  private function syncReviewed(ParagraphInterface $resolution, array $tuples): bool {
    $verdicts = $this->verdicts($resolution);
    $changed = FALSE;

    foreach ($verdicts as $key => $verdict) {
      if ($this->setCurrent($verdict, isset($tuples[$key]))) {
        $changed = TRUE;
      }
    }
    $items = $resolution->get(ContentModel::VERDICTS)->referencedEntities();
    foreach ($tuples as $key => $tuple) {
      if (!isset($verdicts[$key])) {
        $items[] = $this->newVerdict($tuple);
        $changed = TRUE;
      }
    }

    if ($changed) {
      $resolution->set(ContentModel::VERDICTS, $items);
      $resolution->set(ContentModel::RESOLUTION_STATUS, ContentModel::STATUS_STALE);
      $resolution->setNeedsSave(TRUE);
    }
    return $changed;
  }

  /**
   * Checks the saved innovation against the claim groups.
   *
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   */
  private function verify(int $nid, array $groups, array $duplicates): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$nid]);
    $innovation = $storage->load($nid);

    $problems = [];
    $byProperty = [];
    if (!$innovation instanceof NodeInterface) {
      $problems[] = 'the innovation no longer loads';
    }
    else {
      foreach ($innovation->get(ContentModel::RESOLUTIONS)->referencedEntities() as $resolution) {
        $byProperty[$this->property($resolution)][] = $resolution;
      }
    }

    foreach ($byProperty as $property => $resolutions) {
      if (in_array($property, $duplicates, TRUE)) {
        // Pre-existing duplicates were deliberately left alone, and logged.
        continue;
      }
      if (count($resolutions) > 1) {
        $problems[] = "more than one resolution for $property";
        continue;
      }
      $expected = array_keys(($groups[$property] ?? NULL)?->tuples ?? []);
      $current = array_keys(array_filter($this->verdicts($resolutions[0]), fn(ParagraphInterface $v): bool => $this->isCurrent($v)));
      sort($expected);
      sort($current);
      if ($expected !== $current) {
        $problems[] = "the current verdicts for $property do not match the claims";
      }
    }
    foreach ($groups as $property => $group) {
      if ($group->isConflicting() && !isset($byProperty[$property])) {
        $problems[] = "no resolution for conflicting $property";
      }
    }

    if ($problems) {
      $message = sprintf('Claim resolution refresh for innovation %d failed its own check: %s.', $nid, implode('; ', $problems));
      $this->logger->error($message);
      throw new InvariantViolationException($message);
    }
  }

  /**
   * Whether a curator has ruled on, or written in, a resolution.
   */
  private function hasCuratorInput(ParagraphInterface $resolution): bool {
    if ($resolution->get(ContentModel::RESOLUTION_STATUS)->value !== ContentModel::STATUS_PENDING) {
      return TRUE;
    }
    if (trim((string) $resolution->get(ContentModel::RESOLUTION_NOTE)->value) !== '') {
      return TRUE;
    }
    foreach ($this->verdicts($resolution) as $verdict) {
      if (in_array($verdict->get(ContentModel::VERDICT_VALUE)->value, ContentModel::RULINGS, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns a resolution's verdicts keyed by ClaimTuple key.
   *
   * @return array<string, \Drupal\paragraphs\ParagraphInterface>
   *   Verdict paragraphs.
   */
  private function verdicts(ParagraphInterface $resolution): array {
    $out = [];
    foreach ($resolution->get(ContentModel::VERDICTS)->referencedEntities() as $verdict) {
      if ($verdict instanceof ParagraphInterface) {
        $out[$this->verdictKey($verdict)] = $verdict;
      }
    }
    ksort($out);
    return $out;
  }

  /**
   * Rebuilds the ClaimTuple key a verdict was stored under.
   */
  public function verdictKey(ParagraphInterface $verdict): string {
    return ClaimTuple::buildKey(
      (int) $verdict->get(ContentModel::VERDICT_COLLECTION)->target_id,
      (int) $verdict->get(ContentModel::VERDICT_PROPERTY)->target_id,
      (string) $verdict->get(ContentModel::VERDICT_VALUE_KEY)->value,
    );
  }

  /**
   * Whether every verdict is flagged current.
   */
  private function allCurrent(array $verdicts): bool {
    foreach ($verdicts as $verdict) {
      if (!$this->isCurrent($verdict)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Whether a verdict's value is still claimed.
   */
  private function isCurrent(ParagraphInterface $verdict): bool {
    return (bool) $verdict->get(ContentModel::VERDICT_CURRENT)->value;
  }

  /**
   * Sets a verdict's current flag.
   *
   * @return bool
   *   Whether the flag changed.
   */
  private function setCurrent(ParagraphInterface $verdict, bool $current): bool {
    if ($this->isCurrent($verdict) === $current) {
      return FALSE;
    }
    $verdict->set(ContentModel::VERDICT_CURRENT, $current);
    $verdict->setNeedsSave(TRUE);
    return TRUE;
  }

  /**
   * Returns the ATIO property a resolution is about.
   */
  private function property(ParagraphInterface $resolution): string {
    return (string) $resolution->get(ContentModel::RESOLUTION_PROPERTY)->value;
  }

  /**
   * Creates a pending resolution for one property.
   */
  private function newResolution(string $property, array $tuples): ParagraphInterface {
    $resolution = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => ContentModel::RESOLUTION,
      ContentModel::RESOLUTION_PROPERTY => $property,
      ContentModel::RESOLUTION_STATUS => ContentModel::STATUS_PENDING,
      ContentModel::VERDICTS => array_values(array_map(fn(ClaimTuple $tuple): ParagraphInterface => $this->newVerdict($tuple), $tuples)),
    ]);
    assert($resolution instanceof ParagraphInterface);
    return $resolution;
  }

  /**
   * Creates an unreviewed, current verdict for one claimed value.
   */
  private function newVerdict(ClaimTuple $tuple): ParagraphInterface {
    $verdict = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => ContentModel::VERDICT,
      ContentModel::VERDICT_COLLECTION => $tuple->collectionNid,
      ContentModel::VERDICT_PROPERTY => $tuple->propertyTid,
      ContentModel::VERDICT_VALUE_KEY => $tuple->valueKey,
      ContentModel::VERDICT_VALUE_LABEL => mb_substr($tuple->valueLabel, 0, 255),
      ContentModel::VERDICT_VALUE => ContentModel::UNREVIEWED,
      ContentModel::VERDICT_CURRENT => TRUE,
    ]);
    assert($verdict instanceof ParagraphInterface);
    return $verdict;
  }

}
