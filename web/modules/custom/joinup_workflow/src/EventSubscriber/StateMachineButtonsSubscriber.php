<?php declare(strict_types = 1);

namespace Drupal\joinup_workflow\EventSubscriber;

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

    $stateId = $event->getStateId();
    $workflow = $entity->getWorkflow();
    $transition = $workflow->findTransition($stateId, $stateId);

    // @todo: Group the exceptions later when all of them are here.
    // `asset_release` `draft` button should not be update.
    if ($entity->bundle() === 'asset_release' && $stateId === 'draft') {
      return;
    }

    if ($transition) {
      // When the transition is to the same state, change the label to 'Update'.
      // @todo Add here a list of cases where the label should not be 'Update'.
      $event->updateLabel($transition->getId(), 'Update');
    }
  }

}
