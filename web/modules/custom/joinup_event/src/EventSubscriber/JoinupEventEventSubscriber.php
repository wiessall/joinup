<?php

declare(strict_types=1);

namespace Drupal\joinup_event\EventSubscriber;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for events.
 *
 * What do you mean, this is confusing?
 */
readonly class JoinupEventEventSubscriber implements EventSubscriberInterface {

  protected const string UPCOMING_EVENTS_UUID = '9270e98e-cfe2-4eeb-aeae-688a7240a4ff';

  public function __construct(
    protected InboundPathProcessorInterface $pathProcessor,
    protected EntityRepositoryInterface $entityRepository,
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
   * Redirects /events to the upcoming events page.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    // Retrieve the request path, and do inbound processing so that language
    // prefixes are removed.
    $request = $event->getRequest();
    if (str_starts_with($request->getPathInfo(), '/system/files/') && !$request->query->has('file')) {
      // Private files paths are split by the inbound path processor and the
      // relative file path is moved to the 'file' query string parameter. This
      // is because the route system does not allow an arbitrary amount of
      // parameters. We preserve the path as is returned by the request object.
      // @see \Drupal\system\PathProcessor\PathProcessorFiles::processInbound()
      // @see \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber::onKernelRequestCheckRedirect()
      $path = $request->getPathInfo();
    }
    else {
      $path = $this->pathProcessor->processInbound($request->getPathInfo(), $request);
    }

    if ($path === '/events') {
      $events = $this->entityRepository->loadEntityByUuid('node', self::UPCOMING_EVENTS_UUID);
      $response = new LocalRedirectResponse($events->toUrl()->toString(), Response::HTTP_FOUND);
      $event->setResponse($response);
    }
  }

}
