<?php

declare(strict_types=1);

namespace Drupal\custom_page\EventSubscriber;

use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to handle actions on workflow transition.
 */
class CustomPageWorkflowTransition implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'state_machine.pre_transition' => ['handleAction', -100],
    ];
  }

  /**
   * Handle action based on the workflow.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The state change event.
   */
  public function handleAction(WorkflowTransitionEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof CustomPageInterface) {
      return;
    }

    $entity->isDefaultRevision(TRUE);
  }

}
