<?php

declare(strict_types=1);

namespace Drupal\topic\EventSubscriber;

use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\topic\Entity\TopicInterface;
use Drupal\topic\PathProcessor\TopicPathProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Redirects topic term canonical to search page.
 */
class TopicRedirectRequestSubscriber implements EventSubscriberInterface {

  public function __construct(protected RouterInterface $router) {}

  /**
   * Redirects topic term canonical to search page.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    try {
      // It is too early to use current route match, we have to use request
      // matching.
      $match = $this->router->matchRequest($event->getRequest());
      // Fill in keys in route defaults what we use later.
      $match += ['_route' => NULL, '_entity' => NULL, '_route_object' => NULL];
    }
    catch (\Exception) {
      return;
    }

    if (
      $match['_route'] !== 'entity.taxonomy_term.canonical' ||
      !$match['_entity'] instanceof TopicInterface ||
      !$match['_route_object']?->getOption(TopicPathProcessor::REDIRECT_TO_SEARCH_PAGE_OPTION)
    ) {
      return;
    }

    $searchUrl = Url::fromRoute(
      'view.search.page_1',
      [],
      [
        'query' => [
          'f' => [
            'topic:' . $match['_entity']->id(),
          ],
        ],
      ],
    );

    $event->setResponse(
      (new LocalRedirectResponse(
        $searchUrl->toString(),
        Response::HTTP_MOVED_PERMANENTLY
      ))->addCacheableDependency($match['_entity'])
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => [
        ['onKernelRequest', 32],
      ],
    ];
  }

}
