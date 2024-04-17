<?php

declare(strict_types=1);

namespace Drupal\contact_information\Entity;

use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\node\Entity\Node;

/**
 * Bundle class for the 'contact_information' bundle.
 */
class ContactInformation extends Node implements ContactInformationInterface {

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
  public function getRelatedGroup(): ?GroupInterface {
    /** @var \Drupal\joinup_group\Entity\GroupInterface|null $group */
    return $this->get('related_group')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return $this->entityTypeManager()->getDefinition($this->getEntityTypeId());
  }

}
