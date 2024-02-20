<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
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
   *
   * @return bool
   *   TRUE if the transition is allowed, FALSE otherwise.
   */
  public function allowedByPermission(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    \assert(property_exists($this, 'currentUser'), 'currentUser property is missing');
    \assert(property_exists($this, 'workflowHelper'), 'workflowHelper property is missing');
    if ($this->currentUser->hasPermission($entity->getEntityType()->getAdminPermission())) {
      return TRUE;
    }

    $permission = StateMachinePermissionStringConstructor::constructWorkflowTransitionPermission($workflow, $transition);
    if ($this->currentUser->hasPermission($permission)) {
      return TRUE;
    }

    // If the entity is a group, check if the user has the permission on the
    // group. However, if the group is a solution, and the entity is new, then
    // check if the user has the permission on the parent collection because for
    // creation of solutions, the permissions reside in the collection
    // facilitators.
    if ($entity instanceof GroupInterface) {
      if ($entity->bundle() === 'solution' && $entity->isNew()) {
        $entity = $entity->getGroup();
      }
      return $this->workflowHelper->hasOgPermission($permission, $entity, $this->currentUser);
    }
    // For Group content, check if the user has the permission on the parent
    // group.
    elseif ($entity instanceof GroupContentInterface) {
      return $this->workflowHelper->hasOgPermission($permission, $entity->getGroup(), $this->currentUser);
    }

    return $this->currentUser->hasPermission($permission) || $this->workflowHelper->hasOgPermission($permission, $entity, $this->currentUser);
  }

}
