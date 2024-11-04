<?php

declare(strict_types=1);

namespace Drupal\owner\Entity;

use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\node\Entity\Node;

/**
 * Bundle class for the 'owner' bundle.
 */
class Owner extends Node implements OwnerInterface {

  use EntityWorkflowStateTrait;
  use JoinupBundleClassFieldAccessTrait;
  use RdfSyncEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function isReferencedByGroups(): bool {
    return count($this->getReferencingGroupIds()) > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencingGroups(): array {
    if (empty($this->id())) {
      return [];
    }

    $groupIds = $this->getReferencingGroupIds();
    return !empty($groupIds) ? Node::loadMultiple($groupIds) : [];
  }

  /**
   * Returns the ID of the groups which are referencing this owner.
   *
   * @return string[]|int[]
   *   An array of groups IDs that reference the owner, keyed by revision IDs.
   */
  protected function getReferencingGroupIds(): array {
    return \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', ['collection', 'solution'], 'IN')
      ->condition('owner', $this->id())
      ->execute();
  }

}
