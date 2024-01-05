<?php

declare(strict_types = 1);

namespace Drupal\solution\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SolutionArchiveEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new SolutionArchiveEventSubscriber instance.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'solution.archive.post_transition' => ['onSolutionPostArchiveTransition'],
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
    if (!($solution = $event->getEntity()) instanceof SolutionInterface) {
      return;
    }

    $queue = $this->queueFactory->get('solution_archive_related_content');
    $queue->createQueue();
    $queue->createItem([
      'user' => $this->currentUser->id(),
      'solution' => $solution->id(),
    ]);
  }

}
