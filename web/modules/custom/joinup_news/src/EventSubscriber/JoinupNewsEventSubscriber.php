<?php

declare(strict_types=1);

namespace Drupal\joinup_news\EventSubscriber;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for news.
 */
readonly class JoinupNewsEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected InboundPathProcessorInterface $pathProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // This needs to run before RouterListener::onKernelRequest(), which has a
      // priority of 32. Otherwise, that aborts the request if no matching route
      // is found.
      KernelEvents::REQUEST => [['onKernelRequest', 33]],
    ];
  }

  /**
   * Handles kernel request events.
   *
   * Do inbound processing so that language prefixes are removed. Skip private
   * file paths as they are handled separately.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   *
   * @see \Drupal\system\PathProcessor\PathProcessorFiles::processInbound()
   * @see \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber::onKernelRequestCheckRedirect()
   */
  public function onKernelRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (strpos($request->getPathInfo(), '/system/files/') !== 0 || $request->query->has('file')) {
      $this->pathProcessor->processInbound($request->getPathInfo(), $request);
    }
  }

}
