<?php

declare(strict_types = 1);

namespace Drupal\joinup_discussion;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines a field item list class for the discussion's latest update time field.
 */
class LatestUpdateTimeFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * Compute the values.
   */
  protected function computeValue(){
    $some_calculated_values = [1,2,3];
    foreach($some_calculated_values as $delta => $value){
      $this->list[$delta] = $this->createItem($delta, $value);
    }
  }
}
