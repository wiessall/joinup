<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * The OSS Solution short description field, falling back to git description.
 */
class ShortDescriptionWithFallback extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();
    $this->list = [];
    $field = [];
    foreach (['oss_short_description', 'oss_git_description'] as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      $field = $entity->get($fieldName);
      break;
    }

    foreach ($field as $delta => $item) {
      assert($item instanceof FieldItemInterface);
      $this->list[$delta] = $item;
    }
  }

}
