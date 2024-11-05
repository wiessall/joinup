<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_solution\Transition;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listener of the event when a solution gets archived or unarchived.
 */
class SolutionArchiveEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new SolutionArchiveEventSubscriber instance.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user account proxy.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
    protected AccountProxyInterface $currentUser,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'solution.archive.post_transition' => ['onSolutionPostArchiveTransition'],
      'solution.unarchive.post_transition' => ['onSolutionPostUnarchiveTransition'],
    ];
  }

  /**
   * Queues some referenced entities of an archived solution to be archived.
   *
   * Releases, distributions, custom pages and community content of an archived
   * solution must be queued to be archived too.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event dispatched after a solution was archived.
   */
  public function onSolutionPostArchiveTransition(WorkflowTransitionEvent $event): void {
    if (!$this->queueSolutionSubContentTransition($event, Transition::ARCHIVE)) {
      return;
    }

    // The event is only fired if an actual transition happened, so it is safe
    // to set the message here.
    $this->messenger->addStatus(
      $this->t('The archiving process of the Solution has been initiated. You will receive an email notification as soon as the process is completed.')
    );
  }

  /**
   * Queues referenced entities of an unarchived solution to be unarchived.
   *
   * Archived releases, distributions, custom pages and community content of an
   * unarchived solution must be queued to be unarchived too.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event dispatched after a solution was unarchived.
   */
  public function onSolutionPostUnarchiveTransition(WorkflowTransitionEvent $event): void {
    if (!$this->queueSolutionSubContentTransition($event, Transition::UNARCIVE)) {
      return;
    }

    $this->messenger->addStatus(
      $this->t('The unarchiving process of the Solution has been initiated.')
    );
  }

  /**
   * Queues the solution's sub-content being archived / unarchived.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   * @param \Drupal\joinup_solution\Transition $transition
   *   The transition to apply on sub content of a solution if it was
   *   (un)archived.
   *
   * @return bool
   *   Whether queueing was successful.
   */
  public function queueSolutionSubContentTransition(WorkflowTransitionEvent $event, Transition $transition): bool {
    if (!($solution = $event->getEntity()) instanceof SolutionInterface) {
      return FALSE;
    }

    $queue = $this->queueFactory->get('solution_archive_related_content');
    $queue->createQueue();
    $queue->createItem([
      'user' => $this->currentUser->id(),
      'solution' => $solution->id(),
      'transition' => $transition,
    ]);
    return TRUE;
  }

}
