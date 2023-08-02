<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Event;

use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event triggered just after a solution has been fetched from the provider.
 */
class EuOssPostFetchEvent extends Event {

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution $fetchedSolution
   *   The fetched solution object.
   */
  public function __construct(
    protected EuOssCatalogueFetchedSolution $fetchedSolution
  ) {}

  /**
   * Returns the fetched solution.
   *
   * @return \Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution
   *   The fetches solution object.
   */
  public function getFetchedSolution(): EuOssCatalogueFetchedSolution {
    return $this->fetchedSolution;
  }

}
