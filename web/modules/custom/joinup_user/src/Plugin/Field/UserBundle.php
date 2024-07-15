<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Dummy computed field to make up for user account's missing bundle property.
 */
class UserBundle extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $this->list[0] = $this->createItem(0, ['value' => 'user']);
  }

}
