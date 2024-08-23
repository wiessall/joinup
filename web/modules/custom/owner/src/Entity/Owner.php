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
  public function getWorkflowStateFieldName(): string {
    return 'field_state';
  }

  /**
   * {@inheritdoc}
   */
  public function isReferencedByGroups(): bool {
    $count = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', ['collection', 'solution'])
      ->condition('owner', $this->id())
      ->count()
      ->execute();

    return $count > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencingGroups(): array {
    if (empty($this->id())) {
      return [];
    }

    $groupIds = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', ['collection', 'solution'])
      ->condition('owner', $this->id())
      ->execute();

    return !empty($result) ? Node::loadMultiple($groupIds) : [];
  }

}
