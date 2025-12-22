<?php

declare(strict_types=1);

namespace Drupal\assessments\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Permission-based guard for assessment report transitions.
 */
class AssessmentReportPermissionGuard implements GuardInterface {

  use TransitionGuardTrait;

  public function __construct(
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    if ($workflow->getGroup() !== 'assessments') {
      return TRUE;
    }

    return $this->allowedByPermission(
      $transition,
      $workflow,
      $entity,
      TRUE,
      FALSE
    );
  }

}
