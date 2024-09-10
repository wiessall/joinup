<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;

/**
 * Defines a field item list class for the 'distributions' field.
 */
class DistributionsFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();

    if ($entity instanceof SolutionInterface) {
      $distributionIds = \Drupal::entityQuery('node')->accessCheck(FALSE)
        ->condition('type', 'distribution')
        ->condition('og_audience', $entity->id())
        ->notExists('release')
        ->execute();
    }
    elseif ($entity instanceof ReleaseInterface) {
      $distributionIds = \Drupal::entityQuery('node')->accessCheck(FALSE)
        ->condition('type', 'distribution')
        ->condition('release', $entity->id())
        ->execute();
    }
    else {
      return;
    }

    foreach ($distributionIds as $delta => $id) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $id]);
    }
  }

}
