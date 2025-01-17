<?php

declare(strict_types=1);

namespace Drupal\joinup_eulogin\Event\Subscriber;

use Drupal\externalauth\AuthmapInterface;
use Drupal\joinup_user\Event\JoinupUserCancelEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts on user cancelling.
 */
readonly class JoinupEuLoginUserCancelSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected AuthmapInterface $authmap,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'joinup_user.cancel' => 'onUserCancel',
    ];
  }

  /**
   * Removes the CAS linkage when a user is cancelled.
   *
   * @param \Drupal\joinup_user\Event\JoinupUserCancelEvent $event
   *   The user cancel event.
   */
  public function onUserCancel(JoinupUserCancelEvent $event): void {
    $this->authmap->delete((int) $event->getAccount()->id(), 'cas');
  }

}
