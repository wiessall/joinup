<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines a field item list class for the 'distributions' field.
 */
class DistributionsFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if ($this->getEntity()->id()) {
      $distributionIds = \Drupal::getContainer()
        ->get('joinup_distribution.parent_distribution_resolver')
        ->getParentsDistributionIds($this->getEntity()->id());
      foreach ($distributionIds as $delta => $id) {
        $this->list[$delta] = $this->createItem($delta, ['target_id' => $id]);
      }
    }
  }

}
