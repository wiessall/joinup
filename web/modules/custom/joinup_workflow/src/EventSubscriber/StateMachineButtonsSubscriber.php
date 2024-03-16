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
    $stateId = $event->getStateId();
    $workflow = $entity->getWorkflow();
    $transition = $workflow->findTransition($stateId, $stateId);

    if ($transition) {
      // When the transition is to the same state, change the label to 'Update'.
      // @todo Add here a list of cases where the label should not be 'Update'.
      $event->updateLabel($transition->getId(), 'Update');
    }
  }

}
