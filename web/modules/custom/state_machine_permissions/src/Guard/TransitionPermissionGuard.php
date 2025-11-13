<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\owner\Entity\OwnerInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\user\EntityOwnerInterface;

/**
 * Generic guard class that checks the transition permissions.
 */
class TransitionPermissionGuard implements GuardInterface {

  use TransitionGuardTrait;

  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly WorkflowHelperInterface $workflowHelper,
  ) {}

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

    $admin_permission = $entity->getEntityType()->getAdminPermission();
    if ($admin_permission && $this->currentUser->hasPermission($admin_permission)) {
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
