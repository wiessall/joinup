<?php

declare(strict_types=1);

namespace Drupal\owner\Entity;

use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;

/**
 * Interface for owner entities in Joinup.
 */
interface OwnerInterface extends NodeInterface, EntityWorkflowStateInterface, RdfSyncEntityInterface {

  /**
   * Returns whether the owner is referenced by groups.
   */
  public function isReferencedByGroups(): bool;

  /**
   * Returns the groups that reference the owner.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   An array of groups that reference the owner.
   */
  public function getReferencingGroups(): array;

}
