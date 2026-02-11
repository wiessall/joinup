<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\EventSubscriber;

use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Denies access to SPDX licence canonical route for all users.
 *
 * @todo Drop this EventSubscriber in ISAICP-10824.
 */
class SpdxLicenceAccessSubscriber implements EventSubscriberInterface {

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
   * Denies access to SPDX licence canonical route.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if ($route_name !== 'entity.taxonomy_term.canonical') {
      return;
    }

    $term = $request->attributes->get('taxonomy_term');
    if (!$term instanceof TermInterface || $term->bundle() !== 'spdx_licence') {
      return;
    }

    // Nobody should access the SPDX licence canonical route.
    throw new AccessDeniedHttpException('SPDX licences should not be accessed via canonical route.');
  }

}
