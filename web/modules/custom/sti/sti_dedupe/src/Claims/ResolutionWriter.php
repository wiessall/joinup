<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\Exception\InvariantViolationException;
use Drupal\sti_dedupe\Exception\StaleResolutionException;

/**
 * Saves a curator's ruling on one claim resolution.
 *
 * A ruling covers every value currently claimed for the property: each gets
 * authoritative, supplementary or rejected, and exactly one is
 * authoritative, because the property is single-valued. Verdicts on values
 * no longer claimed are history and are not touched.
 *
 * The ruling is recorded only. Writing the authoritative value into the
 * innovation's own field is left to the curator or the enrichment pipeline;
 * there is no property-to-field write path yet, by decision.
 *
 * The innovation is saved as a new revision, attributed to the curator.
 */
final class ResolutionWriter {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly ResolutionReconciler $reconciler,
    private readonly TimeInterface $time,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Records a ruling.
   *
   * @param \Drupal\node\NodeInterface $innovation
   *   The innovation holding the resolution.
   * @param int $resolutionId
   *   The claim_resolution paragraph id.
   * @param array<string, string> $rulings
   *   Verdict per ClaimTuple key, for exactly the currently claimed values.
   * @param string $note
   *   The curator's reasoning; may be empty.
   * @param \Drupal\Core\Session\AccountInterface $curator
   *   Who ruled.
   *
   * @throws \InvalidArgumentException
   *   When the resolution is not on this innovation, a verdict is unknown,
   *   or not exactly one value is authoritative.
   * @throws \Drupal\sti_dedupe\Exception\StaleResolutionException
   *   When the claimed values changed since the ruling was prepared.
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   *   When the saved ruling does not read back as written. Rolled back.
   */
  public function resolve(NodeInterface $innovation, int $resolutionId, array $rulings, string $note, AccountInterface $curator): void {
    $resolution = $this->findResolution($innovation, $resolutionId);
    $current = $this->currentVerdicts($resolution);

    $expected = array_keys($current);
    $given = array_keys($rulings);
    sort($expected);
    sort($given);
    if ($expected !== $given) {
      throw new StaleResolutionException('The claimed values changed since this resolution was loaded. Reload it and rule again.');
    }
    self::validate($rulings);

    foreach ($current as $key => $verdict) {
      if ($verdict->get(ContentModel::VERDICT_VALUE)->value !== $rulings[$key]) {
        $verdict->set(ContentModel::VERDICT_VALUE, $rulings[$key]);
        $verdict->setNeedsSave(TRUE);
      }
    }
    $resolution->set(ContentModel::RESOLUTION_STATUS, ContentModel::STATUS_RESOLVED);
    $resolution->set(ContentModel::RESOLUTION_NOTE, $note);
    $resolution->set(ContentModel::RESOLVED_BY, (int) $curator->id());
    $resolution->set(ContentModel::RESOLVED_AT, $this->time->getRequestTime());
    $resolution->setNeedsSave(TRUE);

    $transaction = $this->database->startTransaction();
    try {
      $innovation->setNewRevision(TRUE);
      $innovation->setRevisionUserId((int) $curator->id());
      $innovation->setRevisionLogMessage(sprintf('Claim resolution for %s.', $resolution->get(ContentModel::RESOLUTION_PROPERTY)->value));
      $innovation->save();
      $this->verify((int) $innovation->id(), $resolutionId, $rulings);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Validates a set of rulings, independent of any stored state.
   *
   * @param array<string, string> $rulings
   *   Verdict per key.
   *
   * @throws \InvalidArgumentException
   *   When a verdict is not a ruling or not exactly one is authoritative.
   */
  public static function validate(array $rulings): void {
    foreach ($rulings as $key => $ruling) {
      if (!in_array($ruling, ContentModel::RULINGS, TRUE)) {
        throw new \InvalidArgumentException(sprintf('"%s" is not a valid verdict for %s.', $ruling, $key));
      }
    }
    $authoritative = count(array_filter($rulings, static fn(string $ruling): bool => $ruling === ContentModel::AUTHORITATIVE));
    if ($authoritative !== 1) {
      throw new \InvalidArgumentException(sprintf('Exactly one value must be authoritative; %d are.', $authoritative));
    }
  }

  /**
   * Returns the current verdicts of a resolution, keyed by ClaimTuple key.
   *
   * @return array<string, \Drupal\paragraphs\ParagraphInterface>
   *   Verdicts whose value is still claimed.
   */
  public function currentVerdicts(ParagraphInterface $resolution): array {
    $out = [];
    foreach ($resolution->get(ContentModel::VERDICTS)->referencedEntities() as $verdict) {
      if ($verdict instanceof ParagraphInterface && $verdict->get(ContentModel::VERDICT_CURRENT)->value) {
        $out[$this->reconciler->verdictKey($verdict)] = $verdict;
      }
    }
    ksort($out);
    return $out;
  }

  /**
   * Finds a resolution among an innovation's own.
   */
  private function findResolution(NodeInterface $innovation, int $resolutionId): ParagraphInterface {
    if ($innovation->hasField(ContentModel::RESOLUTIONS)) {
      foreach ($innovation->get(ContentModel::RESOLUTIONS)->referencedEntities() as $resolution) {
        if ($resolution instanceof ParagraphInterface && (int) $resolution->id() === $resolutionId) {
          return $resolution;
        }
      }
    }
    throw new \InvalidArgumentException(sprintf('Resolution %d is not on innovation %d.', $resolutionId, $innovation->id()));
  }

  /**
   * Reads the ruling back from storage.
   *
   * @throws \Drupal\sti_dedupe\Exception\InvariantViolationException
   */
  private function verify(int $nid, int $resolutionId, array $rulings): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$nid]);
    $innovation = $storage->load($nid);

    $problem = NULL;
    $resolution = NULL;
    if ($innovation instanceof NodeInterface) {
      foreach ($innovation->get(ContentModel::RESOLUTIONS)->referencedEntities() as $candidate) {
        // A new host revision may give the paragraph a new revision, never a
        // new id, so the id is a stable handle here.
        if ((int) $candidate->id() === $resolutionId) {
          $resolution = $candidate;
        }
      }
    }
    if (!$resolution instanceof ParagraphInterface) {
      $problem = 'the resolution is no longer on the innovation';
    }
    elseif ($resolution->get(ContentModel::RESOLUTION_STATUS)->value !== ContentModel::STATUS_RESOLVED) {
      $problem = 'the resolution is not marked resolved';
    }
    else {
      $stored = array_map(
        static fn(ParagraphInterface $verdict): string => (string) $verdict->get(ContentModel::VERDICT_VALUE)->value,
        $this->currentVerdicts($resolution),
      );
      ksort($rulings);
      if ($stored !== $rulings) {
        $problem = 'the stored verdicts differ from the ruling';
      }
    }

    if ($problem !== NULL) {
      $message = sprintf('Claim ruling on resolution %d of innovation %d failed its own check: %s.', $resolutionId, $nid, $problem);
      $this->logger->error($message);
      throw new InvariantViolationException($message);
    }
  }

}
