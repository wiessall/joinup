<?php

declare(strict_types = 1);

namespace Drupal\state_machine_permissions\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_workflow\WorkflowHelper;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;

/**
 * Generic guard class that checks the transition permissions.
 */
class PermissionGuard implements GuardInterface {

  /**
   * Instantiates a PermissionGuard plugin.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   * @param \Drupal\joinup_workflow\WorkflowHelper $workflowHelper
   *   The workflow helper service.
   */
  public function __construct(protected AccountInterface $currentUser, protected WorkflowHelper $workflowHelper) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
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
