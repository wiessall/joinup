<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion;

use Drupal\changed_fields\ObserverInterface;
use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Drupal\joinup_discussion\Event\DiscussionEvents;
use Drupal\joinup_discussion\Event\DiscussionUpdateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Triggers a DiscussionUpdateEvent when fields in a discussion are updated.
 *
 * @see \Drupal\changed_fields\ObserverInterface
 */
readonly class DiscussionObserver implements ObserverInterface {

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

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
          'keywords',
          'field_attachment',
          'field_state',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function update(\SplSubject $subject): void {
    /** @var \Drupal\changed_fields\EntitySubject $subject */
    /** @var \Drupal\node\NodeInterface $discussion */
    $discussion = $subject->getEntity();
    if (!$discussion instanceof DiscussionInterface) {
      return;
    }

    $changed_fields = $subject->getChangedFields();
    $state = $discussion->get('field_state')->getString();
    $was_archived = $discussion->getOriginal()?->isArchived() ?? FALSE;

    // Dispatch the update event only if there are changes of relevant fields
    // and the discussion is in the 'published' state
    // and previous state was not archived.
    if ($changed_fields && $state === 'published' && !$was_archived) {
      $event = new DiscussionUpdateEvent($discussion, $changed_fields);
      $this->eventDispatcher->dispatch($event, DiscussionEvents::UPDATE);
    }
  }

}
