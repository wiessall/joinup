<?php

declare(strict_types=1);

namespace Drupal\solution\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\rdf_entity_provenance\ProvenanceHelperInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Guard class for the transitions of the solution entity.
 */
class SolutionFulfillmentGuard implements GuardInterface {

  use TransitionGuardTrait;

  /**
   * Instantiates a SolutionFulfillmentGuard service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged-in user.
   * @param \Drupal\rdf_entity_provenance\ProvenanceHelperInterface $provenanceHelper
   *   Provenance helper.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected ProvenanceHelperInterface $provenanceHelper
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    // Federated solutions cannot have the `archive` and `unarchived`
    // transitions applied.
    if (in_array($transition->getId(), ['archive', 'unarchive'], TRUE) && $this->isFederated($entity)) {
      return FALSE;
    }

    /** @var \Drupal\solution\Entity\SolutionInterface $entity */
    return $this->allowedByPermission($transition, $workflow, $entity, FALSE, TRUE, $entity->getGroup());
  }

  /**
   * Checks if entity is federated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return bool
   *   TRUE if it is federated, FALSE otherwise.
   */
  protected function isFederated(EntityInterface $entity): bool {
    $activity = $this->provenanceHelper->loadProvenanceActivity($entity->id());
    return !empty($activity);
  }

}
