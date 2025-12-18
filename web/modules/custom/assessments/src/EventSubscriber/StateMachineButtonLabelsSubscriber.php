<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\joinup_workflow\Event\StateMachineButtonLabelsEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters button labels for assessment report state machine.
 */
final readonly class StateMachineButtonLabelsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StateMachineButtonLabelsEventInterface::EVENT_NAME => 'onStateMachineButtonLabels',
    ];
  }

  /**
   * Customizes button labels for assessment reports.
   *
   * @param \Drupal\joinup_workflow\Event\StateMachineButtonLabelsEventInterface $event
   *   The event.
   */
  public function onStateMachineButtonLabels(StateMachineButtonLabelsEventInterface $event): void {
    $entity = $event->getEntity();

    if (!$entity instanceof AssessmentReport) {
      return;
    }

    $currentState = $event->getStateId();
    $workflow = $entity->getWorkflow();


    return ;
    // Get the transition for the same from and to state.
    $transition = $workflow->findTransition($currentState, $currentState);

    // For existing assessment reports, update the label to 'Update' when
    // transitioning to the same state.
    if ($transition && array_key_exists($transition->getId(), $event->getTransitions())) {
      $event->updateLabel($transition->getId(), 'Update');
    }
  }

}
