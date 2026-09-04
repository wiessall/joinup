<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\EventSubscriber;

use Drupal\node\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscribers for the certificate node.
 */
class CertificateSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 30],
    ];
  }

  /**
   * Blocks access for certificate edit/delete form.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    if (!in_array($route_name, [
      'entity.node.edit_form',
      'entity.node.delete_form',
    ], TRUE)) {
      return;
    }

    $node = $request->attributes->get('node');

    if ($node instanceof NodeInterface && $node->bundle() === 'certificate') {
      throw new NotFoundHttpException();
    }
  }

}
