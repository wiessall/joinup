<?php

declare(strict_types=1);

namespace Drupal\owner\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Guard class for the transitions of the owner entity.
 */
class OwnerTransitionGuard implements GuardInterface {

  use TransitionGuardTrait;

  /**
   * Instantiates a OwnerFulfillmentGuard service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged in user.
   */
  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    return $this->allowedByPermission($transition, $workflow, $entity, TRUE, FALSE, NULL);
  }

}
