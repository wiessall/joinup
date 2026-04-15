<?php

declare(strict_types=1);

namespace Drupal\collection\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects /node/add/collection to /propose/collection.
 */
class CollectionRedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  /**
   * Redirects the node add collection route to the propose form.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'node.add') {
      return;
    }

    $nodeType = $request->attributes->get('node_type');
    if ($nodeType?->id() === 'collection') {
      $url = Url::fromRoute('collection.propose_form')->toString();
      $event->setResponse(new RedirectResponse($url, 301));
    }
  }

}
