<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint_piwik\EventSubscriber;

use Drupal\sparql_endpoint_piwik\EndpointTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle finished responses for the anonymous user.
 */
class EndpointResponseSubscriber implements EventSubscriberInterface {

  public function __construct(protected EndpointTracker $endpointTracker) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = 'onRespond';
    return $events;
  }

  /**
   * Event listener for `KernelEvents::RESPONSE` event.
   *
   * @param ResponseEvent $event
   *   The event object.
   */
  public function onRespond(ResponseEvent $event): void {
    if ($this->trackRequest($event)) {
      $this->endpointTracker->track($event->getRequest());
    }
  }

  /**
   * Determines if the request should be tracked.
   *
   * @param ResponseEvent $event
   *   The response event.
   *
   * @return bool
   *   Returns TRUE if the request should be tracked, otherwise FALSE.
   */
  protected function trackRequest(ResponseEvent $event): bool {
    if (!$event->isMainRequest()) {
      return FALSE;
    }

    // Truck only specific route.
    if ($event->getRequest()->attributes->get('_route') !== 'sparql_endpoint.query') {
      return FALSE;
    }

    // Don't truck is response is HTML as it will be picked by the default tracking.
    if (str_contains($event->getResponse()->headers->get('Content-Type', ''), 'text/html')) {
      return FALSE;
    }

    return TRUE;
  }

}
