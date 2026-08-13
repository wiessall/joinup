<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Computed field holding the IDs of the nodes pinned in a group.
 *
 * @see joinup_group_entity_bundle_field_info_alter()
 */
class PinnedContentIdsFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $group = $this->getEntity();
    assert($group instanceof GroupInterface);

    $result = \Drupal::entityTypeManager()
      ->getStorage('meta_entity')
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'pinned_in')
      ->condition('field_pinned_in', $group->id())
      ->condition('target.target_type', 'node')
      ->groupBy('target.target_id')
      ->execute();

    foreach (array_column($result, 'target__target_id') as $delta => $nodeId) {
      $this->list[$delta] = $this->createItem($delta, (int) $nodeId);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE): void {
  }

}
