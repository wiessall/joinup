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
   * @param string $ownership
   *   The ownership type. Either 'own' or 'any'.
   *
   * @return string
   *   The permission string.
   */
  public static function constructTransitionPermission(WorkflowInterface $workflow, WorkflowTransition $transition, string $ownership): string {
    return "perform {$workflow->getId()} workflow {$transition->getId()} transition on $ownership entity";
  }

}
