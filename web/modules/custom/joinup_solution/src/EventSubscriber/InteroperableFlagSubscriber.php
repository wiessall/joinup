<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\Event\FlagEvents;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\flag\FlaggingInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Interoperable flag subscriber.
 */
class InteroperableFlagSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ContentEntityTrackingManager $trackingManager,
    protected LoggerChannelInterface $logger,
    protected AccountInterface $currentUser
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
      $this->onFlagChange($flagging, FALSE);
    }
  }

  /**
   * React to flagging event.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function onFlag(FlaggingEvent $event): void {
    $this->onFlagChange($event->getFlagging(), TRUE);
  }

  /**
   * Handles flag change.
   *
   * @param \Drupal\flag\FlaggingInterface $flagging
   *   Flagging.
   * @param bool $created
   *   True if flagging was created.
   */
  public function onFlagChange(FlaggingInterface $flagging, bool $created): void {
    if ($flagging->getFlagId() !== 'interoperability') {
      return;
    }

    $solution = $flagging->getFlaggable();
    if ($solution instanceof SolutionInterface) {
      $this->trackingManager->trackEntityChange($solution);

      $this->logger->info('Interoperable flag was changed in @group by @user.', [
        '@user' => $this->currentUser->getDisplayName(),
        '@group' => $solution->label(),
        'type' => $created ? 'joinup:flag_interoperable' : 'joinup:unflag_interoperable',
      ]);
    }
  }

}
