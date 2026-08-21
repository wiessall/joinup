<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
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
    if (!$event->shouldCreateItem() || $event->getPlugin() !== 'content') {
      return;
    }

    if (!$this->shouldTranslate($event)) {
      $event->setShouldCreateItem(FALSE);
    }
  }

  /**
   * Determines whether the event's source should be scheduled for translation.
   *
   * @param \Drupal\tmgmt\Events\ShouldCreateJobEvent $event
   *   The event instance.
   *
   * @return bool
   *   TRUE if a job item should be created for the source.
   */
  protected function shouldTranslate(ShouldCreateJobEvent $event): bool {
    $job = $event->getJob();

    // Only continuous content jobs handled by eTranslation are scheduled here.
    if (
      !$job->isContinuous()
      || $job->getState() != JobInterface::STATE_CONTINUOUS
      || $job->getTranslator()->id() !== ETranslationTranslator::ID
    ) {
      return FALSE;
    }

    // Load the default revision (published, for content with a live version).
    $storage = $this->entityTypeManager->getStorage($event->getItemType());
    $entity = $storage->load($event->getItemId());
    if (!$entity instanceof ContentEntityInterface) {
      return FALSE;
    }

    // Only published, non-archived content is translated.
    if (
      ($entity instanceof EntityPublishedInterface && !$entity->isPublished())
      || ($entity instanceof ArchivableEntityInterface && $entity->isArchived())
    ) {
      return FALSE;
    }

    // Skip pending forward revisions (drafts). TMGMT translates the latest
    // revision, so it would translate the draft as unpublished content;
    // accepting that translation is then either refused while the pending
    // revision exists (untranslatable composite fields) or saved into yet
    // another forward revision. Publishing the draft re-triggers scheduling
    // on the default.
    /* @see \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource::saveTranslation */
    /* @see \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource::getPendingRevisionWithCompositeReferenceField() */
    if ($storage instanceof RevisionableStorageInterface && (int) $storage->getLatestRevisionId($entity->id()) !== (int) $entity->getRevisionId()) {
      return FALSE;
    }

    // The entity must expose translatable content.
    if (!$this->translationHelper->hasTranslatableFields($entity)) {
      return FALSE;
    }

    return TRUE;
  }

}
