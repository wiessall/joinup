<?php

declare(strict_types = 1);

namespace Drupal\collection\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\FulfillmentGuardTrait;
use Drupal\joinup_workflow\WorkflowHelper;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Guard class for the transitions of the collection entity.
 */
class CollectionFulfillmentGuard implements GuardInterface {

  use FulfillmentGuardTrait;

  /**
   * Instantiates a CollectionFulfillmentGuard service.
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
    return $this->allowedByPermission($transition, $workflow, $entity);
  }

}
