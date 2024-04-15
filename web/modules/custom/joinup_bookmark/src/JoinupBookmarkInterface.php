<?php

declare(strict_types=1);

namespace Drupal\joinup_bookmark;

/**
 * Provides an interface for joinup_bookmark service.
 */
interface JoinupBookmarkInterface {

  /**
   * Generates a bookmark link.
   *
   * @return array
   *   Bookmark link.
   */
  public function getBookmarkLink(): array;

}
