<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Which owners lack basic information, and marking them for enrichment.
 *
 * An owner is incomplete without a webpage, without a description, or with a description shorter than
 * MIN_DESCRIPTION characters. Requesting an update sets the record status INCOMPLETE and changes nothing else.
 * The workflow state is not used: for a published owner, Joinup saves a change to needs_update as a draft behind
 * the published revision, which the review list, the pull and the applier would not see.
 */
final class Completeness {

  public const MIN_DESCRIPTION = 80;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RecordStatus $recordStatus,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * What the owner lacks; empty when complete.
   *
   * @return string[]
   */
  public static function gaps(NodeInterface $owner): array {
    $gaps = [];
    if ($owner->hasField('webpage') && $owner->get('webpage')->isEmpty()) {
      $gaps[] = 'no webpage';
    }
    if ($owner->hasField('description')) {
      $description = trim(strip_tags((string) $owner->get('description')->value));
      if ($description === '') {
        $gaps[] = 'no description';
      }
      elseif (mb_strlen($description) < self::MIN_DESCRIPTION) {
        $gaps[] = 'description under ' . self::MIN_DESCRIPTION . ' characters';
      }
    }
    return $gaps;
  }

  /**
   * Why the owner is not marked: already in the review queue, mid-review, or complete. NULL when it should be.
   */
  public function skipReason(NodeInterface $owner): ?string {
    if ($owner->get('field_state')->value === 'needs_update') {
      return 'already in needs_update';
    }
    if ($this->recordStatus->is($owner, RecordStatus::INCOMPLETE, RecordStatus::AI, RecordStatus::AI_ONLY_EMPTY, RecordStatus::REJECTED)) {
      return 'already marked, or an AI proposal is in review';
    }
    return self::gaps($owner) ? NULL : 'complete';
  }

  /**
   * Ids of owners that requestUpdate() would mark.
   *
   * @return int[]
   */
  public function incompleteIds(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'owner')->execute();
    $out = [];
    foreach (array_chunk($ids, 200) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $owner) {
        if ($this->skipReason($owner) === NULL) {
          $out[] = (int) $owner->id();
        }
      }
      $storage->resetCache($chunk);
    }
    return $out;
  }

  /**
   * Marks an incomplete owner for enrichment; its state and publication stay as they are.
   *
   * @return string|null
   *   NULL when done, else why it was skipped.
   */
  public function requestUpdate(NodeInterface $owner): ?string {
    if (($reason = $this->skipReason($owner)) !== NULL) {
      return $reason;
    }
    [$state, $published] = [$owner->get('field_state')->value, $owner->isPublished()];
    $this->recordStatus->set($owner, RecordStatus::INCOMPLETE);
    $owner->setNewRevision(TRUE);
    $owner->setRevisionLogMessage('Marked for enrichment: ' . implode(', ', self::gaps($owner)) . '.');
    $owner->save();

    // A workflow hook could make this a draft or change the state; the save reports success either way.
    $saved = $this->entityTypeManager->getStorage('node')->loadUnchanged($owner->id());
    if (!$this->recordStatus->is($saved, RecordStatus::INCOMPLETE) || $saved->get('field_state')->value !== $state || $saved->isPublished() !== $published) {
      $this->logger->error('Owner @id: marked for enrichment, but the current revision has state @state, published=@published and not the record status.', [
        '@id' => $owner->id(),
        '@state' => $saved->get('field_state')->value,
        '@published' => (int) $saved->isPublished(),
      ]);
      throw new \RuntimeException("Owner {$owner->id()}: the mark did not save on the current revision.");
    }
    return NULL;
  }

}
