<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine\WorkflowManagerInterface;

/**
 * Guard class for custom page group.
 */
class CustomPageGuard implements GuardInterface {

  /**
   * Constructs a new CustomPageGuard object.
   *
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflowManager
   *   Workflow manager.
   */
  public function __construct(protected WorkflowManagerInterface $workflowManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    $toState = $transition->getToState()->getId();
    $fromState = $entity->getWorkflowState();

    if (!$entity instanceof CustomPageInterface) {
      return FALSE;
    }

    if ($entity->isGroupLandingPage() && $toState === 'unpublished') {
      return FALSE;
    }

    // Prevent un-archiving content of an archived parent.
    if ($fromState === 'archived' && $toState === 'published' && $this->isParentArchived($entity)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Checks if parent is archived.
   *
   * @param \Drupal\custom_page\Entity\CustomPageInterface $entity
   *   Entity.
   *
   * @return bool
   *   TRUE if parent is archived.
   */
  protected function isParentArchived(CustomPageInterface $entity): bool {
    if (!$entity->hasGroup()) {
      return FALSE;
    }

    $parentGroup = $entity->getGroup();
    if ($parentGroup instanceof ArchivableEntityInterface && $parentGroup->isArchived()) {
      return TRUE;
    }

    return FALSE;
  }

}
