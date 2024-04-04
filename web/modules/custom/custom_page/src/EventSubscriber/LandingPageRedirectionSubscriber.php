<?php

declare(strict_types=1);

namespace Drupal\custom_page\EventSubscriber;

use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\custom_page\Entity\CustomPageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber for redirecting custom nodes to landing route.
 */
class LandingPageRedirectionSubscriber implements EventSubscriberInterface {

  /**
   * Constructs landing page redirection.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $routeMatch
   *   Route match.
   */
  public function __construct(
    protected CurrentRouteMatch $routeMatch,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['nodeViewRedirect'];
    return $events;
  }

  /**
   * Reacts on request event.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Request event.
   */
  public function nodeViewRedirect(RequestEvent $event): void {
    if (!$this->isNodeCanonicalRoute()) {
      return;
    }

    if (($customPage = $this->getCustomPage()) && $customPage->isGroupLandingPage()) {
      $group = $customPage->getGroup();

      $response = new CacheableRedirectResponse($group->toUrl()->toString());
      $response->addCacheableDependency($group);

      $event->setResponse($response);
    }
  }

  /**
   * Checks if route is node canonical.
   *
   * @return bool
   *   True if canonical.
   */
  protected function isNodeCanonicalRoute(): bool {
    return $this->routeMatch->getRouteName() === 'entity.node.canonical';
  }

  /**
   * Gets custom page.
   *
   * @return \Drupal\custom_page\Entity\CustomPageInterface|null
   *   Custom page.
   */
  protected function getCustomPage(): ?CustomPageInterface {
    $node = $this->routeMatch->getParameter('node');

    return $node instanceof CustomPageInterface ? $node : NULL;
  }

}
