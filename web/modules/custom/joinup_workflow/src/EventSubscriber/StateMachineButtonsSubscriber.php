<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_workflow\Event\StateMachineButtonLabelsEventInterface;
use Drupal\state_machine_permissions\StateMachinePermissionStringConstructor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters buttons labels for the state machine.
 */
final class StateMachineButtonsSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new StateMachineButtonsSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current logged-in user.
   */
  public function __construct(protected AccountProxyInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StateMachineButtonLabelsEventInterface::EVENT_NAME => 'onStateMachineButtonLabels',
    ];
  }

  /**
   * Case-specific button labels change.
   *
   * @param \Drupal\joinup_workflow\Event\StateMachineButtonLabelsEventInterface $event
   *   The event.
   */
  public function onStateMachineButtonLabels(StateMachineButtonLabelsEventInterface $event): void {
    $entity = $event->getEntity();
    if ($entity->isNew()) {
      // In case of a new entity, use the default transitions, to avoid setting
      // the 'Save as Draft' button label to 'Update'.
      return;
    }

    $currentState = $event->getStateId();
    $workflow = $entity->getWorkflow();
    if ($entity instanceof CommunityContentInterface) {
      // Use findTransition because it checks for the available transitions.
      if ($currentState === 'published' && $requestChangesTransition = $workflow->findTransition('published', 'proposed')) {
        $group = $entity->getGroup();
        $anyPermission = StateMachinePermissionStringConstructor::constructTransitionPermission($workflow, $workflow->getTransition('publish'), 'any');
        if ($group->hasGroupPermission($this->currentUser->id(), $anyPermission)) {
          // The 'Propose changes' button should be 'Request changes' when
          // user is a facilitator. Use the 'publish' 'any' permission to
          // identify if the user is a facilitator.
          $event->updateLabel($requestChangesTransition->getId(), 'Request changes');
        }
      }
    }

    // Get the transition for the same from and to state. In the following
    // checks, if the transition exists, it means that the transition is allowed
    // and can be handled.
    $transition = $workflow->findTransition($currentState, $currentState);

    // The following cases are exceptions where the label should not be
    // 'Update' when the transition is to the same state:
    // - Asset release in draft state.
    // - Collection and solution entities in general.
    // - Custom pages and community content for the 'save_as_draft' transition.
    if ($entity->bundle() === 'asset_release' && $currentState === 'draft') {
      return;
    }

    if ($entity->bundle() === 'collection' || $entity->bundle() === 'solution') {
      return;
    }

    if (
      ($entity instanceof CommunityContentInterface || $entity instanceof CustomPageInterface)
      && $transition && $transition->getId() === 'save_as_draft') {
      return;
    }

    if ($transition && array_key_exists($transition->getId(), $event->getTransitions())) {
      $event->updateLabel($transition->getId(), 'Update');
    }
  }

}
