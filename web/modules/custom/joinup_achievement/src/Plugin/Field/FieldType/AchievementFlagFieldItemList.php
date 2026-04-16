<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_achievement\AchievementService;

/**
 * Computed field item list for the user achievement flags field.
 *
 * Populates the field with Flagging entities that represent achievements
 * awarded to the parent user, sorted alphabetically by flag ID for a
 * consistent display order across all profiles.
 */
class AchievementFlagFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $user = $this->getEntity();
    if ($user->isNew()) {
      return;
    }

    $flagIds = \Drupal::service(AchievementService::class)
      ->getAchievementFlagIds();

    $flagging_ids = \Drupal::entityQuery('flagging')
      ->accessCheck(FALSE)
      ->condition('entity_type', 'user')
      ->condition('entity_id', $user->id())
      ->condition('flag_id', $flagIds, 'IN')
      ->sort('flag_id')
      ->execute();

    if (empty($flagging_ids)) {
      return;
    }

    $flaggings = \Drupal::entityTypeManager()
      ->getStorage('flagging')
      ->loadMultiple($flagging_ids);

    foreach (array_values($flaggings) as $delta => $flagging) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $flagging->id()]);
    }
  }

}
