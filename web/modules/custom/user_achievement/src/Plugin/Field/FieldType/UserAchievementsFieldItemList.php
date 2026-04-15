<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field item list for the user achievements field.
 *
 * Populates the field with the Achievement entities linked to the parent user,
 * sorted alphabetically by bundle for a consistent display order across all
 * profiles.
 */
class UserAchievementsFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $user = $this->getEntity();
    if ($user->isNew()) {
      return;
    }

    $achievements = \Drupal::entityTypeManager()
      ->getStorage('user_achievement')
      ->loadByProperties(['uid' => $user->id()]);

    if (empty($achievements)) {
      return;
    }

    uasort($achievements, static fn($a, $b) => strcmp($a->bundle(), $b->bundle()));

    foreach (array_values($achievements) as $delta => $achievement) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $achievement->id()]);
    }
  }

}
