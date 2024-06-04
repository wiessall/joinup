<?php

declare(strict_types=1);

namespace Drupal\solution;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\og\Og;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Read-only field item list class for the solution's 'distribution' field.
 */
class SolutionDistributionFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();

    // Check for proper usage.
    assert($entity instanceof SolutionInterface);
    assert($this->getName() === 'distribution');

    if ($entity->id()) {
      foreach ($this->getDistributionIds() as $nid) {
        $this->list[0] = $this->createItem(0, ['target_id' => $nid]);
      }
    }
  }

  /**
   * Returns a list of distribution IDs linked directly to the solution.
   *
   * @return int[]
   *   A list of distribution IDs linked directly to the solution.
   */
  protected function getDistributionIds(): array {
    $nids = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('type', 'distribution')
      ->condition('status', TRUE)
      ->condition('og_audience.target_id', $this->getEntity()->id())
      ->execute();
    return array_values(array_map('intval', $nids));
  }

}
