<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Owners with a pending AI proposal, by the collections whose solutions reference them.
 *
 * Nothing records which import created an owner; an owner belongs to every collection that uses it, so either
 * collection's curators can review it.
 */
final class OwnerQueue {

  private const SOLUTION = 'solution';
  private const OWNER_FIELD = 'owner';
  private const COLLECTION_FIELD = 'og_audience';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * @return array<int, list<int>>
   *   Owner ids with a pending proposal, keyed by collection id.
   */
  public function byCollection(?int $collectionId = NULL): array {
    $fields = $this->entityFieldManager->getFieldDefinitions('node', self::SOLUTION);
    if (!isset($fields[self::OWNER_FIELD], $fields[self::COLLECTION_FIELD])) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', self::SOLUTION)
      ->exists(self::OWNER_FIELD . '.entity.' . ProposalApplier::FIELD);
    if ($collectionId !== NULL) {
      $query->condition(self::COLLECTION_FIELD, $collectionId);
    }
    $owners = [];
    foreach (array_chunk($query->execute(), 200) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $solution) {
        $collection = (int) $solution->get(self::COLLECTION_FIELD)->target_id;
        foreach ($solution->get(self::OWNER_FIELD) as $item) {
          $owners[$collection][(int) $item->target_id] = TRUE;
        }
      }
      $storage->resetCache($chunk);
    }
    $out = [];
    foreach ($owners as $collection => $ids) {
      foreach ($storage->loadMultiple(array_keys($ids)) as $owner) {
        if ($owner instanceof NodeInterface && ProposalApplier::isPending($owner)) {
          $out[$collection][] = (int) $owner->id();
        }
      }
    }
    return $out;
  }

}
