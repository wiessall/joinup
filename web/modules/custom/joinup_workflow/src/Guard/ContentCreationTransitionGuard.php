<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Content creation transition guard.
 */
readonly class ContentCreationTransitionGuard implements GuardInterface {

  public function __construct(
    protected AccountInterface $currentUser,
    protected WorkflowHelperInterface $workflowHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    if (!$entity->isNew()) {
      // True is the neutral return because otherwise, the transition will be
      // blocked.
      return TRUE;
    }
    $group = $entity->getGroup();
    if (!$group instanceof GroupInterface) {
      return FALSE;
    }

    $permissionString = sprintf('create content for %s option', $group->getContentCreators());
    return $this->currentUser->hasPermission($permissionString) || $group->hasGroupPermission($this->currentUser->id(), $permissionString);
  }

}
