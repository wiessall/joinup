<?php

declare(strict_types = 1);

namespace Drupal\joinup_group;

/**
 * Provide an interface for group archivable entities.
 */
interface GroupArchivableEntityInterface {

  /**
   * Checks whether the parent's entity is archived.
   *
   * @return bool
   *   Whether group entity is archived.
   */
  public function isParentArchived(): bool;

}
