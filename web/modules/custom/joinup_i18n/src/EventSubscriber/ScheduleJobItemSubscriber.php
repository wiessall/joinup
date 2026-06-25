<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_i18n\TranslationHelper;
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
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TranslationHelper $translationHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ContinuousEvents::SHOULD_CREATE_JOB => 'scheduleJobItemCreation'];
  }

  /**
   * Checks if a job item should be created.
   *
   * @param \Drupal\tmgmt\Events\ShouldCreateJobEvent $event
   *   The event instance.
   */
  public function scheduleJobItemCreation(ShouldCreateJobEvent $event): void {
    if (!$event->shouldCreateItem()) {
      // An upstream subscriber has denied the creation of the job item.
      return;
    }

    $job = $event->getJob();

    $shouldCreateItem =
      // The source plugin is 'content'.
      $event->getPlugin() === 'content'
      // This is a continuous job.
      && $job->isContinuous()
      // The job is in continuous state.
      && $job->getState() == JobInterface::STATE_CONTINUOUS
      // The job is using the eTranslation translator.
      && $job->getTranslator()->id() === ETranslationTranslator::ID
      // The entity exists.
      && ($entity = $this->entityTypeManager->getStorage($event->getItemType())->load($event->getItemId()))
      // Only non-publishable or public entities are translated.
      && (!($entity instanceof EntityPublishedInterface) || $entity->isPublished())
      // Only non-archivable or non-archived entities are translated.
      && (!($entity instanceof ArchivableEntityInterface) || !$entity->isArchived())
      // The entity actually exposes translatable content.
      && $entity instanceof ContentEntityInterface
      && $this->translationHelper->hasTranslatableFields($entity);

    $event->setShouldCreateItem($shouldCreateItem);
  }

}
