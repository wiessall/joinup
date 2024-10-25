<?php

declare(strict_types=1);

namespace Drupal\joinup_tour;

/**
 * Helper methods for dealing with tours on Portal.
 */
interface TourHelperInterface {

  /**
   * Returns whether the current page has a tour.
   *
   * @return bool
   *   TRUE if a tour is present on the current page.
   */
  public function currentPageHasTour(): bool;

}
