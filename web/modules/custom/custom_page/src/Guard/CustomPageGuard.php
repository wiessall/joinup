<?php

declare(strict_types=1);

namespace Drupal\custom_page\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Guard class for custom page entity.
 */
readonly class CustomPageGuard implements GuardInterface {

  public function __construct(
    #[Autowire(service: 'plugin.manager.workflow')]
    protected WorkflowManagerInterface $workflowManager,
  ) {}

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
    if ($fromState === 'archived' && $toState === 'published' && $entity->isParentArchived()) {
      return FALSE;
    }

    return TRUE;
  }

}
