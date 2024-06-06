<?php

declare(strict_types=1);

namespace Drupal\state_machine_revisions\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\state_machine_revisions\RevisionManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to handle revisions on workflow-enabled entities.
 */
class WorkflowTransitionEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new WorkflowTransitionEventSubscriber object.
   *
   * @param \Drupal\state_machine_revisions\RevisionManagerInterface $revisionManager
   *   The revision manager.
   * @param \Drupal\joinup_workflow\WorkflowHelperInterface $workflowHelper
   *   The workflow helper.
   */
  public function __construct(protected RevisionManagerInterface $revisionManager, protected WorkflowHelperInterface $workflowHelper) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'state_machine.pre_transition' => 'handleRevision',
    ];
  }

  /**
   * Sets an entity revision to be the default based on the workflow.
   *
   * This event fires only on a transition i.e. state change. Same from-to
   * states will not fire the event so do not include changes that might take
   * place during a simple update.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The state change event.
   */
  public function handleRevision(WorkflowTransitionEvent $event): void {
    $entity = $event->getEntity();

    // Bail out if this entity doesn't implement revisions. We do not limit
    // strictly to content entities.
    if (!$entity instanceof RevisionableInterface) {
      return;
    }

    // Since all content entities implement the RevisionableInterface, we have
    // to check if the entity has the revision support and if it is set to
    // create a new revision.
    if (!$entity->getEntityType()->isRevisionable() || !$entity->isNewRevision()) {
      return;
    }

    // Set revision as default when:
    // - the entity is new;
    // - the new state is a published one;
    // - the current default revision is not published.
    // @todo Change this to $entity->setDefaultRevision() once issue 2706337 is
    //   in.
    // @see https://www.drupal.org/node/2706337
    $entity->isDefaultRevision(
      $entity->isNew()
      || $this->workflowHelper->isWorkflowStatePublished($event->getTransition()->getToState()->getId(), $event->getWorkflow())
      || !$this->hasPublishedDefaultRevision($entity)
    );
  }

  /**
   * Checks if an entity has a published default revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the entity has a published default revision, FALSE otherwise.
   */
  protected function hasPublishedDefaultRevision(ContentEntityInterface $entity): bool {
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
