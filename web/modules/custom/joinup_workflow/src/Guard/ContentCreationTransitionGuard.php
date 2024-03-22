<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\WorkflowHelper;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Content creation transition guard.
 */
class ContentCreationTransitionGuard implements GuardInterface {

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
    if (!$entity->isNew()) {
      // True is the neutral return because otherwise, the transition will be
      // blocked.
      return TRUE;
    }
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = $entity->getGroup();
    if (empty($group)) {
      return FALSE;
    }

    $permissionString = sprintf('create content for %s option', $group->getContentCreators());
    return $this->currentUser->hasPermission($permissionString) || $group->hasGroupPermission($this->currentUser->id(), $permissionString);
  }

}
