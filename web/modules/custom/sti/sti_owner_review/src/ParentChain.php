<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Walks field_parent_organization upwards.
 */
final class ParentChain {

  /**
   * Longer chains than this are treated as broken, as if they were a cycle.
   */
  public const MAX_DEPTH = 20;

  /**
   * Whether making $parentId the parent of $childId would loop: the parent is the child, or the child is
   * already one of the parent's ancestors.
   */
  public static function wouldCycle(EntityStorageInterface $storage, string $childId, string $parentId): bool {
    $current = $parentId;
    for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
      if ($current === $childId) {
        return TRUE;
      }
      $node = $storage->loadUnchanged($current);
      if (!$node || !$node->hasField(ProposalApplier::PARENT_FIELD) || $node->get(ProposalApplier::PARENT_FIELD)->isEmpty()) {
        return FALSE;
      }
      $current = (string) $node->get(ProposalApplier::PARENT_FIELD)->target_id;
    }
    return TRUE;
  }

}
