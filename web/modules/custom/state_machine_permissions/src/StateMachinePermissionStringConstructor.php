<?php

declare(strict_types=1);

namespace Drupal\state_machine_permissions;

use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Handles generation of permission strings.
 */
class StateMachinePermissionStringConstructor {

  /**
   * Constructs a transition permission.
   *
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowInterface $workflow
   *   The workflow.
   * @param \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition
   *   The transition.
   *
   * @return string
   *   The permission string.
   */
  public static function constructTransitionPermission(WorkflowInterface $workflow, WorkflowTransition $transition): string {
    return "perform {$workflow->getId()} workflow {$transition->getId()} transition";
  }

}
