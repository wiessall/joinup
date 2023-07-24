<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Event;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event triggered just before a fetched solution is saved as node.
 */
class EuOssSolutionPreSaveEvent extends Event {

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution node entity.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution $fetchedSolution
   *   The fetched solution object.
   */
  public function __construct(
    protected OssSolutionInterface $solution,
    public readonly EuOssCatalogueFetchedSolution $fetchedSolution,
  ) {}

  /**
   * Returns the OSS Solution node entity.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface
   *   The OSS Solution node entity.
   */
  public function getSolution(): OssSolutionInterface {
    return $this->solution;
  }

}
