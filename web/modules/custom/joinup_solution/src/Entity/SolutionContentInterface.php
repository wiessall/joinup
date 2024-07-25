<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Entity;

use Drupal\joinup_group\Entity\GroupContentInterface;

/**
 * Interface for entities that are solution content.
 *
 * This comprises asset releases and asset distributions.
 */
interface SolutionContentInterface extends GroupContentInterface {

  /**
   * Returns the solution to which this entity belongs.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface
   *   The solution.
   *
   * @throws \Drupal\joinup_solution\Exception\MissingSolutionException
   *   Thrown when the solution has not been set on the entity.
   */
  public function getSolution(): SolutionInterface;

}
