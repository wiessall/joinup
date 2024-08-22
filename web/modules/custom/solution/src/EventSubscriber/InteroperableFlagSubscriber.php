<?php

declare(strict_types=1);

namespace Drupal\solution\EventSubscriber;

use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\flag\FlaggingInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Drupal\solution\Entity\SolutionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Interoperable flag subscriber.
 */
class InteroperableFlagSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ContentEntityTrackingManager $trackingManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FlagEvents::ENTITY_FLAGGED => ['onFlag'],
      FlagEvents::ENTITY_UNFLAGGED => ['onUnFlag'],
    ];
  }

  /**
   * React to unflagging event.
   *
   * @param \Drupal\flag\Event\UnflaggingEvent $event
   *   The unflagging event.
   */
  public function onUnFlag(UnflaggingEvent $event): void {
    foreach ($event->getFlaggings() as $flagging) {
      $this->onFlagChange($flagging);
    }
  }

  /**
   * React to flagging event.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function onFlag(FlaggingEvent $event): void {
    $this->onFlagChange($event->getFlagging());
  }

  /**
   * Handles flag change.
   *
   * @param \Drupal\flag\FlaggingInterface $flagging
   *   Flagging.
   */
  public function onFlagChange(FlaggingInterface $flagging): void {
    if ($flagging->getFlagId() !== 'interoperability') {
      return;
    }

    $solution = $flagging->getFlaggable();
    if ($solution instanceof SolutionInterface) {
      $this->trackingManager->trackEntityChange($solution);
    }
  }

}
