<?php

declare(strict_types=1);

namespace Drupal\assessments\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Hides submit transition when user has direct publish permissions.
 */
class AssessmentReportSubmitGuard implements GuardInterface {

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

    // Do not display “Submit” as “Publish” is already present
    // and may confuse users.
    if ($transition->getToState()->getId() === 'review' && $this->isAllowedToPublish($transition, $workflow, $entity)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if the current user has permission to publish assessment report.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition
   *   The workflow transition being evaluated.
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow plugin.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The assessment report entity.
   *
   * @return bool
   *   TRUE if user is allowed to use publish_from_draft transition.
   */
  protected function isAllowedToPublish(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    $publish_from_draft = $workflow->getTransition('publish_from_draft');
    if (!$publish_from_draft) {
      return FALSE;
    }

    return $this->allowedByPermission(
      $publish_from_draft,
      $workflow,
      $entity,
      TRUE,
      FALSE
    );
  }

}
