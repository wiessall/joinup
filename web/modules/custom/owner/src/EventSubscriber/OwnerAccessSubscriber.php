<?php

declare(strict_types=1);

namespace Drupal\owner\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\owner\Entity\OwnerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Denies anonymous access to owner canonical route.
 *
 * @todo Drop this EventSubscriber in ISAICP-10824.
 */
class OwnerAccessSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after routing but before the controller.
    return [
      KernelEvents::REQUEST => ['onRequest', 28],
    ];
  }

  /**
   * Denies access to owner canonical route for anonymous users.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if ($route_name !== 'entity.node.canonical') {
      return;
    }

    $node = $request->attributes->get('node');
    if (!$node instanceof OwnerInterface) {
      return;
    }

    // Anonymous users should not access owner canonical route.
    throw new AccessDeniedHttpException('Anonymous users should not see the owner canonical route.');
  }

}
