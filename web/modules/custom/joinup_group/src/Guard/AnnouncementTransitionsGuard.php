<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;

/**
 * Limits the allowed transitions based on transition permissions.
 */
readonly class AnnouncementTransitionsGuard implements GuardInterface {

  public function __construct(
    protected JoinupGroupManagerInterface $groupManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    if ($entity instanceof AnnouncementInterface) {
      $permission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $transition, 'any');
      return $this->groupManager->access($entity->getGroup(), $permission, $permission)->isAllowed();
    }
    throw new \InvalidArgumentException('The ' . __CLASS__ . ' guard can be used only with announcement message entities.');
  }

}
