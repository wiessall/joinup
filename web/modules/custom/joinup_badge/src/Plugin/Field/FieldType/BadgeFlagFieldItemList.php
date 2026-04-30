<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_badge\BadgeService;

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

    $flaggingIds = \Drupal::entityQuery('flagging')
      ->accessCheck(FALSE)
      ->condition('entity_type', 'user')
      ->condition('entity_id', $user->id())
      ->condition('flag_id', array_keys($flags), 'IN')
      ->sort('flag_id')
      ->execute();

    if (empty($flaggingIds)) {
      return;
    }

    $badges = \Drupal::entityTypeManager()
      ->getStorage('flagging')
      ->loadMultiple($flaggingIds);

    foreach (array_values($badges) as $delta => $flagging) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $flagging->id()]);
    }
  }

}
