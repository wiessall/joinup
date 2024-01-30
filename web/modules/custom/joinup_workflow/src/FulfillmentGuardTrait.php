<?php

declare(strict_types = 1);

namespace Drupal\joinup_workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;

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
  public function allowedByPermission(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    $from_state = $entity->getWorkflowState();

    \assert(property_exists($this, 'currentUser'), 'currentUser property is missing');
    \assert(property_exists($this, 'workflowHelper'), 'workflowHelper property is missing');

    if ($this->currentUser->hasPermission($entity->getEntityType()->getAdminPermission())) {
      return TRUE;
    }

    // @todo The transition should be passed in the method instead of searching it.
    $permission = StateMachinePermissionStringConstructor::constructWorkflowTransitionPermission($workflow, $transition);
    return $this->currentUser->hasPermission($permission) || $this->workflowHelper->hasOgPermission($permission, $entity, $this->currentUser);
  }

}
