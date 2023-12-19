<?php

declare(strict_types = 1);

namespace Drupal\asset_distribution\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\workflow_state_permission\WorkflowStatePermissionInterface;

/**
 * Guard class for the transitions of the asset_distribution entity.
 */
class DistributionFulfillmentGuard implements GuardInterface {

  /**
   * Virtual state.
   */
  const NON_STATE = '__new__';

  /**
   * Instantiates a DistributionFulfillmentGuard service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged in user.
   * @param \Drupal\workflow_state_permission\WorkflowStatePermissionInterface $workflowStatePermission
   *   The service that determines the permission to update the workflow state
   *   for a given entity.
   */
  public function __construct(protected AccountInterface $currentUser, protected WorkflowStatePermissionInterface $workflowStatePermission) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    /** @var \Drupal\asset_distribution\Entity\AssetDistributionInterface $entity */
    $to_state = $transition->getToState()->getId();
    // Disable virtual state.
    if ($to_state == self::NON_STATE) {
      return FALSE;
    }

    $from_state = $entity->getWorkflowState();

    // Note that we cannot call $entity->isTargetWorkflowStateAllowed() since it
    // invokes the guards, causing an endless loop.
    return $this->workflowStatePermission->isStateUpdatePermitted($this->currentUser, $entity, $workflow, $from_state, $to_state);
  }

}
