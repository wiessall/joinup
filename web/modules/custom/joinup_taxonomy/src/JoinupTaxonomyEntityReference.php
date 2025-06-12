<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;

/**
 * Override for the EntityReferenceItem class.
 */
class JoinupTaxonomyEntityReference extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public function preSave(): void {
    if ($this->getFieldDefinition()->getName() !== 'keywords') {
      parent::preSave();
    }
    elseif ($this->hasNewEntity()) {
      // Save the entity if it has not already been saved by some other code.
      if ($this->entity->isNew()) {
        // Because this will run before the generic
        // \joinup_workflow_entity_presave hook, we need to get the publication
        // status from the target state instead.
        $entity = $this->getEntity();
        if (!$entity instanceof EntityWorkflowStateInterface || !$entity->hasField('keywords')) {
          return;
        }
        $workflow_helper = \Drupal::service('joinup_workflow.workflow_helper');
        $workflow = $entity->getWorkflow();
        $state_id = $entity->getWorkflowState();
        $is_published = $workflow_helper->isWorkflowStatePublished($state_id, $workflow);
        if (!$is_published) {
          // If the entity is being created in a new unpublished entity, we
          // unpublish it.
          $this->entity->setUnpublished();
        }
        $this->entity->save();
      }

      // Make sure the parent knows we are updating this property, so it can
      // react properly.
      $this->target_id = $this->entity->id();
    }
    if (!$this->isEmpty() && $this->target_id === NULL) {
      $this->target_id = $this->entity->id();
    }
  }

}
