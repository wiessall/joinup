<?php

declare(strict_types=1);

namespace Drupal\owner\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for owner entities in Portal.
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
