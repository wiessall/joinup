<?php

declare(strict_types=1);

namespace Drupal\trr\EventSubscriber;

use EasyRdf\RdfNamespace;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Event subscriber that registers the TRR namespace on every page load.
 */
class RegisterNamespace implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events['kernel.request'] = ['kernelRequest'];
    return $events;
  }

  /**
   * Register the TRR namespace on each page load.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The dispatched event.
   */
  public function kernelRequest(RequestEvent $event): void {
    RdfNamespace::set('trr', 'http://joinup.ec.europa.eu/trr#');
  }

}
