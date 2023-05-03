<?php

declare(strict_types = 1);

namespace Drupal\custom_page\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_workflow\Event\UnchangedWorkflowStateUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscribers for the "joinup_workflow.unchanged_workflow_state_update".
 */
class CustomPageUnchangedWorkflowState implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'joinup_workflow.unchanged_workflow_state_update' => 'onUnchangedWorkflowStateUpdate',
    ];
  }

  /**
   * Determines if the content can be updated without changing workflow state.
   *
   * @param \Drupal\joinup_workflow\Event\UnchangedWorkflowStateUpdateEvent $event
   *   The event.
   */
  public function onUnchangedWorkflowStateUpdate(UnchangedWorkflowStateUpdateEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof CustomPageInterface) {
      return;
    }

    if (in_array($event->getState(), ['__new__', 'archived'])) {
      $access = AccessResult::forbidden();
      $event->setAccess($access);
    }
  }

}
