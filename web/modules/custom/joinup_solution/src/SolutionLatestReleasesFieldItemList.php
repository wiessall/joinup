<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Defines a read-only field item list class for the 'latest_release' field.
 */
class SolutionLatestReleasesFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if ($this->getEntity()->id() && ($latest_release_id = $this->getLatestRelease())) {
      $this->list[0] = $this->createItem(0, ['target_id' => $latest_release_id]);
    }
  }

  /**
   * Returns a reference to solution's latest release.
   *
   * @return int|null
   *   A reference to solution's latest release
   */
  protected function getLatestRelease(): ?int {
    /** @var \Drupal\joinup_solution\Entity\SolutionInterface $solution */
    $solution = $this->getEntity();
    $ids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'release')
      ->condition('og_audience', $solution->id())
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();

    return $ids ? (int) reset($ids) : NULL;
  }

}
