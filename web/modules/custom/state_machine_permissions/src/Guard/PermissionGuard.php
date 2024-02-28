<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_workflow\WorkflowHelper;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Drupal\user\EntityOwnerInterface;

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

    // If the entity is a group, check if the user has the permission on the
    // group. However, if the group is a solution, and the entity is new, then
    // check if the user has the permission on the parent collection because for
    // creation of solutions, the permissions reside in the collection
    // facilitators.
    if ($entity instanceof GroupInterface) {
      if ($entity->bundle() === 'solution' && $entity->isNew()) {
        $entity = $entity->getGroup();
      }
      $permission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'any');
      return $this->workflowHelper->hasOgPermission($permission, $entity, $this->currentUser);
    }

    $anyPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'any');
    if ($this->currentUser->hasPermission($anyPermission) || $this->workflowHelper->hasOgPermission($anyPermission, $entity->getGroup(), $this->currentUser)) {
      return TRUE;
    }

    if (!$entity instanceof EntityOwnerInterface) {
      return FALSE;
    }

    if ($this->currentUser->id() === $entity->getOwnerId()) {
      $ownPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'own');
      return $this->currentUser->hasPermission($ownPermission) || $this->workflowHelper->hasOgPermission($ownPermission, $entity->getGroup(), $this->currentUser);
    }

    return FALSE;
  }

}
