<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\EventSubscriber;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\tmgmt\Events\ContinuousEvents;
use Drupal\tmgmt\Events\ShouldCreateJobEvent;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to \Drupal\tmgmt\Events\ShouldCreateJobEvent event.
 */
final readonly class ScheduleJobItemSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected QueueFactory $queueFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Act at the very end.
      ContinuousEvents::SHOULD_CREATE_JOB => ['scheduleJobItemCreation', -1000],
    ];
  }

  /**
   * Schedules a continuous job item creation to a queue.
   *
   * @param \Drupal\tmgmt\Events\ShouldCreateJobEvent $event
   *   The event instance.
   */
  public function scheduleJobItemCreation(ShouldCreateJobEvent $event): void {
    $job = $event->getJob();

    if (!$event->shouldCreateItem()) {
      // An upstream subscriber has denied the creation of the job item.
      return;
    }

    // Prevent normal job item creation, we'll create the job item in worker.
    $event->setShouldCreateItem(FALSE);

    if (
      // The source plugin is 'content'.
      $event->getPlugin() === 'content'
      // This is a continuous job.
      && $job->isContinuous()
      // The job is in continuous state.
      && $job->getState() == JobInterface::STATE_CONTINUOUS
      // The job is using the eTranslation translator.
      && $job->getTranslator()->id() === ETranslationTranslator::ID
    ) {
      $storage = $this->entityTypeManager->getStorage($event->getItemType());

      if (!$entity = $storage->load($event->getItemId())) {
        // The entity has been deleted in a parallel process.
        return;
      }

      if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
        // Only public entities are translated.
        return;
      }

      if ($entity instanceof ArchivableEntityInterface && $entity->isArchived()) {
        // The entity is archived, we don't translate archived content.
        return;
      }

      $queue = $this->queueFactory->get('joinup_i18n_create_job_items');
      $queue->createQueue();
      $queue->createItem([
        'job' => $event->getJob()->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'entity_id' => $entity->id(),
      ]);
    }
  }

}
