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

    $flaggingIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'user')
      ->condition('entity_id', $user->id())
      ->condition('flag_id', array_keys($flags), 'IN')
      ->execute();

    $badges = [];

    // Map flagging flag ID to flagging ID.
    foreach ($storage->loadMultiple($flaggingIds) as $flaggingId => $flagging) {
      $badges[$flagging->bundle()] = $flaggingId;
    }

    foreach (array_values($flags) as $delta => $flag) {
      $this->list[$delta] = $this->createItem($delta, [
        'target_id' => $badges[$flag->id()] ?? NULL,
      ]);
    }
  }

}
