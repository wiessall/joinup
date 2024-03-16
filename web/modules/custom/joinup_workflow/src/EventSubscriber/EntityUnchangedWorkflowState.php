<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\asset_distribution\Entity\AssetDistributionInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_workflow\Event\UnchangedWorkflowStateUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscribers for the "joinup_workflow.unchanged_workflow_state_update".
 */
class EntityUnchangedWorkflowState implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
//      'joinup_workflow.unchanged_workflow_state_update' => 'onUnchangedWorkflowStateUpdate',
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
    if (!($entity instanceof CustomPageInterface || $entity instanceof AssetDistributionInterface)) {
      return;
    }

    // @todo Now that there are no workarounds for the same from-to state, this
    //   event should be removed and should be handled directly in the workflow.
    //   An exception might be with custom pages.
    if (in_array($event->getState(), ['__new__', 'archived'])) {
      $access = AccessResult::forbidden();
      $event->setAccess($access);
    }
  }

}
