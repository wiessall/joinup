<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines a field item list class for the discussion's latest update time.
 */
class LatestUpdateTimeFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $latest_update_time_formatted = (new \DateTime())->setTimestamp($this->getEntity()->getLatestUpdateTime())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
    $this->list[0] = $this->createItem(0, $latest_update_time_formatted);
  }

}
