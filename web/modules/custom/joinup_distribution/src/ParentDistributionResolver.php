<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper service to resolve the distributions of a parent entity.
 */
class ParentDistributionResolver implements ParentDistributionResolverInterface {

  /**
   * Constructs a ParentResolver object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getParentsDistributionIds(string $parentId): array {
    return $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck()
      ->condition('type', 'distribution')
      ->condition('distribution_parent', $parentId)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getParentsDistributions(string $parentId): array {
    return $this->entityTypeManager->getStorage('node')
      ->loadMultiple($this->getParentsDistributionIds($parentId));
  }

}
