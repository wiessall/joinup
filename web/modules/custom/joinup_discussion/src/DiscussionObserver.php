<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\changed_fields\ObserverInterface;
use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Drupal\joinup_discussion\Event\DiscussionEvents;
use Drupal\joinup_discussion\Event\DiscussionUpdateEvent;
use Drupal\joinup_workflow\ArchivableEntityInterface;

/**
 * Triggers a DiscussionUpdateEvent when fields in a discussion are updated.
 *
 * @see \Drupal\changed_fields\ObserverInterface
 */
class DiscussionObserver implements ObserverInterface {

  /**
   * Builds a new discussion observer.
   *
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   The event dispatcher service.
   */
  public function __construct(protected ContainerAwareEventDispatcher $eventDispatcher) {
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      'node' => [
        'discussion' => [
          'title',
          'body',
          'field_topic',
          'field_keywords',
          'field_attachment',
          'field_state',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function update(\SplSubject $entity_subject): void {
    /** @var \Drupal\changed_fields\EntitySubject $entity_subject */
    /** @var \Drupal\node\NodeInterface $discussion */
    $discussion = $entity_subject->getEntity();
    if (!$discussion instanceof DiscussionInterface) {
      return;
    }

    $changed_fields = $entity_subject->getChangedFields();
    $state = $discussion->get('field_state')->getString();
    $was_archived = isset($discussion->original) && $discussion->original instanceof ArchivableEntityInterface ?
      $discussion->original->isArchived() : FALSE;

    // Dispatch the update event only if there are changes of relevant fields
    // and the discussion is in the 'published' state
    // and previous state was not archived.
    if ($changed_fields && $state === 'published' && !$was_archived) {
      $event = new DiscussionUpdateEvent($discussion, $changed_fields);
      $this->eventDispatcher->dispatch($event, DiscussionEvents::UPDATE);
    }
  }

}
