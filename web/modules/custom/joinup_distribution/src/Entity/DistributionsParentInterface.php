<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Bundle class for content having distributions as children.
 */
interface DistributionsParentInterface extends ContentEntityInterface {

  /**
   * Returns the child distribution IDs.
   *
   * @return int[]
   *   The child distribution IDs.
   */
  public function getDistributionIds(): array;

  /**
   * Returns the distributions belonging to this entity.
   *
   * @return \Drupal\joinup_distribution\Entity\DistributionInterface[]
   *   Distributions belonging to this entity.
   */
  public function getDistributions(): array;

}
