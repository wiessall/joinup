<?php declare(strict_types = 1);

namespace Drupal\joinup_workflow\EventSubscriber;

use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_workflow\Event\StateMachineButtonLabelsEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @todo Add description for this subscriber.
 */
final class StateMachineButtonsSubscriber implements EventSubscriberInterface {

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
        // The 'Propose changes' button should be 'Request changes' when
        // user is a facilitator. There is no permission specific to this.
        // @todo Inject the services.
        $user = \Drupal::currentUser();
        $membershipManager = \Drupal::getContainer()->get('og.membership_manager');
        $group = $event->getEntity()->getGroup();
        if ($membership = $membershipManager->getMembership($group, $user->id())) {
          if ($membership->hasRole("{$group->getEntityTypeId()}-{$group->bundle()}-facilitator")) {
            $event->updateLabel($requestChangesTransition->getId(), 'Request changes');
          }
        }
      }
    }

    // Get the transition for the same from and to state. In the following
    // checks, if the transition exists, it means that the transition is allowed
    // and can be handled.
    $transition = $workflow->findTransition($currentState, $currentState);

    // @todo: Group the exceptions later when all of them are here.
    // `asset_release` `draft` button should not be update.
    if ($entity->bundle() === 'asset_release' && $currentState === 'draft') {
      return;
    }

    if ($entity->bundle() === 'collection' || $entity->bundle() === 'solution') {
      return;
    }

    // Custom pages show 'Save as Draft' button when the transition is
    // 'save_as_draft'. Currently, the transition source states are only __new__
    // and draft, thus we can check only by the transition ID.
    if (($entity instanceof CommunityContentInterface || $entity instanceof CustomPageInterface) && $transition && $transition->getId() === 'save_as_draft') {
      return;
    }

    if ($transition && array_key_exists($transition->getId(), $event->getTransitions())) {
      // When the transition is to the same state, change the label to 'Update'.
      // @todo Add here a list of cases where the label should not be 'Update'.
      $event->updateLabel($transition->getId(), 'Update');
    }
  }

}
