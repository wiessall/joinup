<?php

declare(strict_types = 1);

namespace Drupal\solution\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listener of the event when a solution gets archived.
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

    // The event is only fired if an actual transition happened, so it is safe
    // to set the message here.
    $this->messenger->addStatus(
      $this->t('The archiving process of the Solution has been initiated. You will receive an email notification as soon as the process is completed.')
    );
  }

}
