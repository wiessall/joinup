<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\joinup_workflow\WorkflowHelper;
use Drupal\owner\Entity\OwnerInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\user\EntityOwnerInterface;

/**
 * Generic guard class that checks the transition permissions.
 */
class TransitionPermissionGuard implements GuardInterface {

  use TransitionGuardTrait;

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
    if (
      $entity instanceof CollectionInterface
      || $entity instanceof OwnerInterface
      || $entity instanceof SolutionInterface
      || $entity instanceof ContactInformationInterface
    ) {
      // Handled in their respective modules as there are specific cases for
      // these entities.
      return TRUE;
    }

    if ($this->currentUser->hasPermission($entity->getEntityType()->getAdminPermission())) {
      return TRUE;
    }

    if (!$entity->hasGroup()) {
      // This is a neutral return so that other guards can be checked. This
      // covers cases like custom landing pages where the group is not set.
      return TRUE;
    }

    return $this->allowedByPermission($transition, $workflow, $entity,
      $entity instanceof EntityOwnerInterface,
      TRUE, $entity->getGroup());
  }

}
