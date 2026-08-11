<?php

declare(strict_types=1);

namespace Drupal\joinup_search;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Looks up which content is pinned in a group.
 *
 * The data is read directly from the `pinned_in` meta entity rather than from
 * the search index, so that a pin or an unpin is reflected in the listings on
 * the next request, without waiting for the index to catch up.
 */
class PinnedContentLookup {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the IDs of the nodes pinned in the given group.
   *
   * @param string|int $groupId
   *   The ID of the group.
   *
   * @return int[]
   *   The node IDs, in no particular order.
   */
  public function getPinnedNodeIds(string|int $groupId): array {
    $storage = $this->entityTypeManager->getStorage('meta_entity');

    $metaEntityIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'pinned_in')
      ->condition('field_pinned_in', $groupId)
      ->execute();

    $nodeIds = [];
    foreach ($storage->loadMultiple($metaEntityIds) as $metaEntity) {
      $target = $metaEntity->get('target')->first();
      if ($target?->target_type === 'node') {
        $nodeIds[] = (int) $target->target_id;
      }
    }
    return $nodeIds;
  }

}
