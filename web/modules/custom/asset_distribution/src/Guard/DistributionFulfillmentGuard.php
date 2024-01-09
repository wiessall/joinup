<?php

declare(strict_types = 1);

namespace Drupal\asset_distribution\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\FulfillmentGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\workflow_state_permission\WorkflowStatePermissionInterface;

/**
 * Guard class for the transitions of the asset_distribution entity.
 */
class DistributionFulfillmentGuard implements GuardInterface {

  use FulfillmentGuardTrait;

  /**
   * Instantiates a DistributionFulfillmentGuard service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   * @param \Drupal\workflow_state_permission\WorkflowStatePermissionInterface $workflowStatePermission
   *   The service that determines the permission to update the workflow state
   *   for a given entity.
   */
  public function __construct(protected AccountInterface $currentUser, protected WorkflowStatePermissionInterface $workflowStatePermission) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    return $this->allowedByPermission($transition, $workflow, $entity);
  }

}
