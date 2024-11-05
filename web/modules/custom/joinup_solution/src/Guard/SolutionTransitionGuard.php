<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Guard class for the transitions of the solution entity.
 */
class SolutionTransitionGuard implements GuardInterface {

  use TransitionGuardTrait;

  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    if (!$entity instanceof SolutionInterface) {
      // TRUE is also used as neutral for transition guards as they are looping
      // over all guards until one returns FALSE.
      return TRUE;
    }

    // In case the entity is being created, it was initiated through a
    // collection. Transitions depend on the collection's group permissions. In
    // case the entity is being updated, the group is itself, to allow
    // privileged users to perform transitions.
    $group = $entity->isNew() ? $entity->getGroup() : $entity;
    /** @var \Drupal\joinup_solution\Entity\SolutionInterface $entity */
    return $this->allowedByPermission($transition, $workflow, $entity, FALSE, TRUE, $group);
  }

}
