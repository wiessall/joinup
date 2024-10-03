<?php

declare(strict_types=1);

namespace Drupal\joinup_release;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * A field item list class that link the releases to their solution.
 */
class SolutionReleasesFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $releaseIds = \Drupal::entityQuery('node')
      ->condition('type', 'release')
      ->condition('og_audience', $this->getEntity()->id())
      ->execute();

    foreach ($releaseIds as $delta => $id) {
      $this->list[$delta] = $this->createItem($delta, ['target_id' => $id]);
    }
  }

}
