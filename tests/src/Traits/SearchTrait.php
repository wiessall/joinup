<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

/**
 * Helper methods for dealing with the search engine.
 */
trait SearchTrait {

  /**
   * Find all search boxes.
   *
   * @return array
   *   Search box elements.
   */
  protected function findAllSearchBoxes(): array {
    return $this->getSession()
      ->getPage()
      ->findAll('css', '.joinup-navbar .navbar-search input#search-box');
  }

}
