<?php

declare(strict_types=1);

namespace Drupal\owner\Entity;

use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\rdf_entity\RdfInterface;

/**
 * Interface for owner entities in Joinup.
 */
interface OwnerInterface extends RdfInterface, EntityWorkflowStateInterface {

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
