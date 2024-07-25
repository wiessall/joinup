<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_distribution\Entity\DistributionInterface;

/**
 * Distribution 'parent' field item list class.
 */
class DistributionParentItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $distribution = $this->getEntity();
    assert($distribution instanceof DistributionInterface);

    if (!$distribution->get('release')->isEmpty()) {
      $value = $distribution->get('release')->target_id;
    }
    elseif (!$distribution->get('og_audience')->isEmpty()) {
      $value = $distribution->get('og_audience')->target_id;
    }
    else {
      return;
    }

    $this->list[0] = $this->createItem(0, $value);
  }

  // @todo Feature: $distr->parent = $entity; $distr->save();
}
