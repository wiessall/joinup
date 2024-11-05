<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines a field item list for the solutions field of collections.
 */
class CollectionSolutionsFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if ($this->getEntity()->id()) {
      foreach ($this->getSolutions() as $delta => $id) {
        $this->list[$delta] = $this->createItem($delta, ['target_id' => $id]);
      }
    }
  }

  /**
   * Returns a list of solutions of a collection.
   *
   * @return string[]
   *   A list of solution IDs.
   */
  protected function getSolutions(): array {
    $entityTypeManager = \Drupal::entityTypeManager();
    $storage = $entityTypeManager->getStorage('node');
    $collection = $this->getEntity();
    return array_values($storage
      ->getQuery()
      ->accessCheck()
      ->condition('type', 'solution')
      ->condition('og_audience', $collection->id())
      ->sort('created', 'DESC')
      ->execute());
  }

}
