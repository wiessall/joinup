<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\joinup_group\Entity\GroupContentTrait;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\ArchivableEntityTrait;

/**
 * Implements interface for group archivable entities.
 */
trait GroupArchivableEntityTrait {

  use GroupContentTrait;
  use ArchivableEntityTrait;

  /**
   * Checks whether the parent's entity is archived.
   *
   * @return bool
   *   Whether group entity is archived.
   */
  public function isParentArchived(): bool {
    assert($this instanceof GroupArchivableEntityInterface, 'Classes using ' . __TRAIT__ . ' should implement \Drupal\joinup_group\GroupArchivableEntityInterface');

    if (!$this->hasGroup()) {
      return FALSE;
    }

    $parentGroup = $this->getGroup();
    if ($parentGroup instanceof ArchivableEntityInterface && $parentGroup->isArchived()) {
      return TRUE;
    }

    return FALSE;
  }

}
