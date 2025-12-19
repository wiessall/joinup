<?php

declare(strict_types=1);

namespace Drupal\assessments\Guard;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Transitions guard of assessment entities.
 */
class AssessmentReportTransitionGuard implements GuardInterface {

  use TransitionGuardTrait;

  public function __construct(
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    if (!$entity instanceof AssessmentReport) {
      return TRUE;
    }

    $hasBypas = AccessResult::allowedIfHasPermission(
      $this->currentUser,
      'bypass assessment report moderation')
      ->isAllowed();

    if ($transition->getToState()->getId() === 'published') {
      return $hasBypas;
    }

    // Do not display “Submit” as “Publish” is already present
    // and may confuse users.
    if ($transition->getToState()->getId() === 'review' && $hasBypas) {
      return FALSE;
    }

    return TRUE;
  }

}
