<?php

declare(strict_types=1);

namespace Drupal\collection\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;

/**
 * Guard class for the transitions of the collection entity.
 */
class CollectionGuard implements GuardInterface {

  use TransitionGuardTrait;

  /**
   * Instantiates a CollectionFulfillmentGuard service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   */
  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    if (!$entity instanceof CollectionInterface) {
      // TRUE is also used as neutral for transition guards as they are looping
      // over all guards until one returns FALSE.
      return TRUE;
    }

    if ($entity->isNew()) {
      // For new entities, use the 'own' permission to differentiate from when
      // the entity exists and more roles are involved.
      $permission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'own');
      if ($this->currentUser->hasPermission($permission)) {
        return TRUE;
      }
    }

    return $this->allowedByPermission($transition, $workflow, $entity, FALSE, FALSE);
  }

}
