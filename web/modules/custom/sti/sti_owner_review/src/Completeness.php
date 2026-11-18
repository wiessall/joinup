<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Which owners lack basic information, and putting them in the review queue.
 *
 * An owner is incomplete without a webpage, without a description, or with a description shorter than
 * MIN_DESCRIPTION characters. Requesting an update moves it to needs_update and leaves it published: solutions keep
 * showing it and the graph keeps its triples, while the queue, the enrichment pull and the review pick it up.
 */
final class Completeness {

  public const MIN_DESCRIPTION = 80;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
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
   * Ids of incomplete owners not yet in needs_update.
   *
   * @return int[]
   */
  public function incompleteIds(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'owner')
      ->condition('field_state', 'needs_update', '<>')
      ->execute();
    $out = [];
    foreach (array_chunk($ids, 200) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $owner) {
        if (self::gaps($owner)) {
          $out[] = (int) $owner->id();
        }
      }
      $storage->resetCache($chunk);
    }
    return $out;
  }

  /**
   * Moves an incomplete owner to needs_update, publication unchanged.
   *
   * @return string|null
   *   NULL when done, else why it was skipped.
   */
  public function requestUpdate(NodeInterface $owner): ?string {
    if ($owner->get('field_state')->value === 'needs_update') {
      return 'already in needs_update';
    }
    $gaps = self::gaps($owner);
    if (!$gaps) {
      return 'complete';
    }
    $published = $owner->isPublished();
    $owner->set('field_state', 'needs_update');
    $owner->setNewRevision(TRUE);
    $owner->setRevisionLogMessage('Update requested: ' . implode(', ', $gaps) . '.');
    $owner->save();

    // A workflow hook could change the state or the publication on save; the save reports success either way.
    $saved = $this->entityTypeManager->getStorage('node')->loadUnchanged($owner->id());
    if ($saved->get('field_state')->value !== 'needs_update' || $saved->isPublished() !== $published) {
      $this->logger->error('Owner @id: update requested, but it saved as @state, published=@published.', [
        '@id' => $owner->id(),
        '@state' => $saved->get('field_state')->value,
        '@published' => (int) $saved->isPublished(),
      ]);
      throw new \RuntimeException("Owner {$owner->id()}: the update request did not save as requested.");
    }
    return NULL;
  }

}
