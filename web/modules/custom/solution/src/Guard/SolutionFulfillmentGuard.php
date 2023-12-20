<?php

declare(strict_types = 1);

namespace Drupal\solution\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rdf_entity_provenance\ProvenanceHelperInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\workflow_state_permission\WorkflowStatePermissionInterface;

/**
 * Guard class for the transitions of the solution entity.
 */
class SolutionFulfillmentGuard implements GuardInterface {

  /**
   * Virtual state.
   */
  const NON_STATE = '__new__';

  /**
   * Instantiates a SolutionFulfillmentGuard service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   * @param \Drupal\workflow_state_permission\WorkflowStatePermissionInterface $workflowStatePermission
   *   The service that determines the permission to update the workflow state
   *   for a given entity.
   * @param \Drupal\rdf_entity_provenance\ProvenanceHelperInterface $provenanceHelper
   *   Provenance helper.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected WorkflowStatePermissionInterface $workflowStatePermission,
    protected ProvenanceHelperInterface $provenanceHelper
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    /** @var \Drupal\solution\Entity\SolutionInterface $entity */
    $toState = $transition->getToState()->getId();
    // Disable virtual state.
    if ($toState === self::NON_STATE) {
      return FALSE;
    }

    $fromState = $entity->getWorkflowState();

    // Don't (un)archive federated solutions.
    if (in_array($toState, [
        'archived',
        'unarchive',
      ], TRUE) && $this->isFederated($entity)) {
      return FALSE;
    }

    // Note that we cannot call $entity->isTargetWorkflowStateAllowed() since it
    // invokes the guards, causing an endless loop.
    return $this->workflowStatePermission->isStateUpdatePermitted($this->currentUser, $entity, $workflow, $fromState, $toState);
  }

  /**
   * Checks if entity is federated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return bool
   *  TRUE if it is federated, FALSE otherwise.
   */
  protected function isFederated(EntityInterface $entity): bool {
    $activity = $this->provenanceHelper->loadProvenanceActivity($entity->id());
    return !empty($activity);
  }

}
