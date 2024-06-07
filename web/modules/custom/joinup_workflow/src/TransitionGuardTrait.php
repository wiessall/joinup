<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Drupal\user\EntityOwnerInterface;

/**
 * Base guard class for the transitions of the joinup entity.
 */
trait TransitionGuardTrait {

  /**
   * Checks if the transition is allowed by checking the permission only.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition
   *   The transition.
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param bool $checkOwnerPermission
   *   Whether to check the owner permission. Defaults to TRUE.
   * @param bool $checkGroupPermission
   *   Whether to check the group permission. Defaults to TRUE.
   * @param \Drupal\joinup_group\Entity\GroupInterface|null $group
   *   The group of the entity. Required if $checkGroupPermission is TRUE.
   *
   * @return bool
   *   TRUE if the transition is allowed, FALSE otherwise.
   */
  public function allowedByPermission(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity, bool $checkOwnerPermission = TRUE, bool $checkGroupPermission = TRUE, ?GroupInterface $group = NULL): bool {
    \assert(property_exists($this, 'currentUser'), 'currentUser property is missing');
    if ($this->currentUser->hasPermission($entity->getEntityType()->getAdminPermission())) {
      return TRUE;
    }

    $anyPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'any');
    if ($this->currentUser->hasPermission($anyPermission)) {
      return TRUE;
    }

    $ownPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'own');
    if ($checkOwnerPermission && $entity instanceof EntityOwnerInterface && $this->currentUser->id() === $entity->getOwnerId()) {
      if ($this->currentUser->hasPermission($ownPermission)) {
        return TRUE;
      }
    }

    if (!$checkGroupPermission) {
      return FALSE;
    }

    if ($group === NULL) {
      throw new \InvalidArgumentException('The group must be provided when checking group permissions.');
    }

    if ($group->hasGroupPermission($this->currentUser->id(), $anyPermission)) {
      return TRUE;
    }

    return $checkOwnerPermission && $entity instanceof EntityOwnerInterface && $this->currentUser->id() === $entity->getOwnerId() && $group->hasGroupPermission($this->currentUser->id(), $ownPermission);
  }

}
