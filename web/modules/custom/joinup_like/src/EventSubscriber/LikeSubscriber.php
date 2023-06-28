<?php

declare(strict_types = 1);

namespace Drupal\joinup_like\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for handling flagging and unflagging events.
 */
class LikeSubscriber implements EventSubscriberInterface {

  /**
   * Reacts to a flagging event.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function onFlag(FlaggingEvent $event): void {
    $flagging = $event->getFlagging();
    Cache::invalidateTags($flagging->getFlaggable()->getCacheTagsToInvalidate());
  }

  /**
   * Reacts to an unflagging event.
   *
   * @param \Drupal\flag\Event\UnflaggingEvent $event
   *   The unflagging event.
   */
  public function onUnflag(UnflaggingEvent $event): void {
    $flagging = $event->getFlaggings();
    $flagging = reset($flagging);
    Cache::invalidateTags($flagging->getFlaggable()->getCacheTagsToInvalidate());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[FlagEvents::ENTITY_FLAGGED][] = ['onFlag'];
    $events[FlagEvents::ENTITY_UNFLAGGED][] = ['onUnflag'];
    return $events;
  }

}
