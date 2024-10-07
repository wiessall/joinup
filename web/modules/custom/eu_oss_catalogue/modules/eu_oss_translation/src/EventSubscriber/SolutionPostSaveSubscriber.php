<?php

declare(strict_types=1);

namespace Drupal\eu_oss_translation\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\eu_oss_catalogue\Event\EuOssSolutionPostSaveEvent;
use Drupal\eu_oss_translation\Plugin\QueueWorker\TranslationRequester;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Solution post save.
 */
class SolutionPostSaveSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected QueueFactory $queue,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EuOssSolutionPostSaveEvent::class => ['postSave'],
    ];
  }

  /**
   * Queue after solution was fetched.
   */
  public function postSave(EuOssSolutionPostSaveEvent $event): void {
    $solution = $event->getSolution();

    $queue = $this->queue->get(TranslationRequester::ID);
    $queue->createQueue();
    $queue->createItem(['nid' => $solution->id()]);
  }

}
