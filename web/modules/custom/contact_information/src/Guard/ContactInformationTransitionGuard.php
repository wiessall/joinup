<?php

declare(strict_types=1);

namespace Drupal\contact_information\Guard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_workflow\TransitionGuardTrait;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;

/**
 * Guard class for the transitions of the contact_information entity.
 */
class ContactInformationTransitionGuard implements GuardInterface {

  use TransitionGuardTrait;

  /**
   * Constructs an instance of the ContactInformationFulfillmentGuard service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged in user.
   */
  public function __construct(protected AccountInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public function allowed(WorkflowTransition $transition, WorkflowInterface $workflow, EntityInterface $entity): bool {
    // There are 2 specific permissions for contact information which are:
    // - The user cannot request_deletion if they have access to delete.
    // - The owner cannot request deletion if they are a moderator according
    //   to the test.
    // tests/features/contact_information/contact_information.moderation.feature:112.
    if ($transition->getId() === 'request_deletion' && $entity->access('delete')) {
      return FALSE;
    }

    $group = $entity->getRelatedGroup();
    return $this->allowedByPermission($transition, $workflow, $entity, TRUE, !empty($group), $group);
  }

}
