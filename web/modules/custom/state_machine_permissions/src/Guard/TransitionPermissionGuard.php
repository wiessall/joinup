<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions\Guard;

use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_workflow\WorkflowHelper;
use Drupal\owner\Entity\OwnerInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Drupal\user\EntityOwnerInterface;

/**
 * Generic guard class that checks the transition permissions.
 */
class TransitionPermissionGuard implements GuardInterface {

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

      if ($entity->bundle() === 'collection' && $entity->isNew()) {
        // Look for the 'own' permission on the collection.
        // @todo Add a comment here that we assume ownership for new collections
        //     in order to differentiate from when the entity is not new.
        $permission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'own');
        if ($this->currentUser->hasPermission($permission)) {
          return TRUE;
        }
      }

      $permission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'any');

      if ($this->currentUser->hasPermission($permission)) {
        return TRUE;
      }

      // @todo Should the first check be an instanceof?
      // Collections only have global permissions. Skip for performance.
      return $entity->bundle() !== 'collection' && $this->workflowHelper->hasOgPermission($permission, $entity, $this->currentUser);
    }

    // For owners, we only need to check global permissions.
    if ($entity instanceof OwnerInterface) {
      $is_owner = $this->currentUser->id() === $entity->getOwnerId();
      if ($is_owner) {
        $permission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'own');
        if ($this->currentUser->hasPermission($permission)) {
          return TRUE;
        }
      }

      $permission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'any');
      return $this->currentUser->hasPermission($permission);
    }

    if ($entity instanceof ContactInformationInterface) {
      $group = $entity->getRelatedGroup();
      // This comes from the state permission and is specific to contact
      // information. 2 specific permissions for contact information are:
      // - The user cannot request_deletion if they have access to delete.
      // - The owner cannot request deletion if they are a moderator according
      //   to the test
      // tests/features/contact_information/contact_information.moderation.feature:112
      // To be checked if the second is actually true.
      // @todo Add a note about this.
      if ($transition->getId() === 'request_deletion' && $entity->access('delete')) {
        return FALSE;
      }
    }
    else {
      $group = $entity->getGroup();
    }

    $anyPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'any');
    // @todo Use og.access service here.
    if ($this->currentUser->hasPermission($anyPermission) || $this->workflowHelper->hasOgPermission($anyPermission, $group, $this->currentUser)) {
      return TRUE;
    }

    if (!$entity instanceof EntityOwnerInterface) {
      return FALSE;
    }

    if ($this->currentUser->id() === $entity->getOwnerId()) {
      $ownPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'own');
      return $this->currentUser->hasPermission($ownPermission) || $this->workflowHelper->hasOgPermission($ownPermission, $group, $this->currentUser);
    }

    return FALSE;
  }

}
