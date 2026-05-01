<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_badge\BadgeService;

/**
 * Computed field item list for the user badge flags field.
 */
class BadgeFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  public const string NOT_AWARDED = '0';

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $user = $this->getEntity();
    if ($user->isNew()) {
      return;
    }

    if (!$flags = \Drupal::service(BadgeService::class)->getBadgeFlags()) {
      return;
    }

    $storage = \Drupal::entityTypeManager()->getStorage('flagging');
    $flaggingIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'user')
      ->condition('entity_id', $user->id())
      ->condition('flag_id', array_keys($flags), 'IN')
      ->execute();

    // Map flagging flag ID to flagging ID. Note that a user can only have one
    // flagging of a given type, so we can safely use the first one.
    // @see \Drupal\joinup_badge\BadgeService::award()
    $badges = [];
    foreach ($storage->loadMultiple($flaggingIds) as $flaggingId => $flagging) {
      $badges[$flagging->bundle()] = $flaggingId;
    }

    foreach (array_values($flags) as $delta => $flag) {
      $this->list[$delta] = $this->createItem($delta, [
        // '0' is a valid entity reference target ID. We use it to indicate that
        // the user has not yet been awarded with that type of badge.
        'target_id' => $badges[$flag->id()] ?? self::NOT_AWARDED,
      ]);
    }
  }

}
