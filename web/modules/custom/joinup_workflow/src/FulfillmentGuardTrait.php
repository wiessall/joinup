<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Base guard class for the transitions of the joinup entity.
 */
trait FulfillmentGuardTrait {

  /**
   * Checks if the transition is allowed by checking the permission only.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition
   *   The transition.
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The parent entity.
   */
  public function allowedByPermission(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity) {
    /** @var \Drupal\collection\Entity\CollectionInterface $entity */
    $toState = $transition->getToState()->getId();
    // Disable virtual state.
    if ($toState == WorkflowHelperInterface::NON_STATE) {
      return FALSE;
    }

    $from_state = $entity->getWorkflowState();

    \assert(property_exists($this, 'currentUser'), 'currentUser property is missing');
    \assert(property_exists($this, 'workflowStatePermission'), 'workflowStatePermission property is missing');

    // Note that we cannot call $entity->isTargetWorkflowStateAllowed() since it
    // invokes the guards, causing an endless loop.
    return $this->workflowStatePermission->isStateUpdatePermitted($this->currentUser, $entity, $workflow, $from_state, $toState);
  }

}
