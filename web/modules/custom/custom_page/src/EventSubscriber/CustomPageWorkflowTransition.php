<?php

declare(strict_types=1);

namespace Drupal\custom_page\EventSubscriber;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\state_machine_revisions\RevisionManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to handle actions on workflow transition.
 */
readonly class CustomPageWorkflowTransition implements EventSubscriberInterface {

  public function __construct(
    protected RevisionManagerInterface $revisionManager,
    protected WorkflowHelperInterface $workflowHelper,
  ) {}

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
   * Properly sets revision as default only when appropriate, similar to
   * state_machine_revisions logic but specifically for custom pages.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The state change event.
   */
  public function handleAction(WorkflowTransitionEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof CustomPageInterface) {
      return;
    }

    // Set revision as default when:
    // - the entity is new;
    // - the new state is a published one;
    // - the current default revision is not published.
    $entity->isDefaultRevision(
      $entity->isNew()
      || $this->workflowHelper->isWorkflowStatePublished($event->getTransition()->getToState()->getId(), $event->getWorkflow())
      || !$this->hasPublishedDefaultRevision($entity)
    );
  }

  /**
   * Checks if an entity has a published default revision.
   *
   * @param \Drupal\custom_page\Entity\CustomPageInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity has a published default revision, FALSE otherwise.
   */
  protected function hasPublishedDefaultRevision(CustomPageInterface $entity): bool {
    // New entities don't have revisions, obviously.
    if ($entity->isNew()) {
      return FALSE;
    }

    $defaultRevision = $this->revisionManager->loadDefaultRevision($entity);
    // The entity needs to implement the published interface, or of course it's
    // not published.
    if (!$defaultRevision instanceof EntityPublishedInterface) {
      return FALSE;
    }

    return $defaultRevision->isPublished();
  }

}
