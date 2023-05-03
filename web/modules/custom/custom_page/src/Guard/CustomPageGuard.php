<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
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
    if ($entity instanceof CustomPageInterface && $entity->isGroupLandingPage() && $toState === 'unpublished') {
      return FALSE;
    }

    return TRUE;
  }

}
