<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_badge\BadgeService;
use Drupal\joinup_badge\Entity\BadgeAwardingInterface;

/**
 * Computed field item list for the user badge flags field.
 */
class BadgeFlagFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $user = $this->getEntity();
    if ($user->isNew()) {
      return;
    }

    $flags = \Drupal::service(BadgeService::class)->getBadgeFlags();

    $storage = \Drupal::entityTypeManager()->getStorage('flagging');

    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'user')
      ->condition('entity_id', $user->id())
      ->condition('flag_id', array_keys($flags), 'IN')
      ->execute();

    if (empty($ids)) {
      return;
    }

    $badges = array_values($storage->loadMultiple($ids));

    // Sort by the flag weight.
    usort($badges, function (BadgeAwardingInterface $a, BadgeAwardingInterface $b): int {
      return $a->getFlag()->getWeight() <=> $b->getFlag()->getWeight();
    });

    foreach ($badges as $delta => $flagging) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $flagging->id()]);
    }
  }

}
