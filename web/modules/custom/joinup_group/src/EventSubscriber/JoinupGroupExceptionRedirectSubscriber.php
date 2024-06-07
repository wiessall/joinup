<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\Core\Routing\RouteMatch;
use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirect to the group page on access denied.
 *
 * When a user reaches a route which results in access denied, such as
 * view.discussions.overview, redirect to the group page.
 */
class JoinupGroupExceptionRedirectSubscriber implements EventSubscriberInterface {

  /**
   * A list of applicable routes.
   */
  private const ROUTES = ['view.discussions.overview'];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::EXCEPTION => 'onExceptionAccessDenied',
    ];
  }

  /**
   * Detect access denied exceptions on group-related routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onExceptionAccessDenied(ExceptionEvent $event): void {
    if (!$event->isMainRequest() || !$event->getThrowable() instanceof AccessDeniedHttpException) {
      return;
    }

    $route_match = RouteMatch::createFromRequest($event->getRequest());
    if (!in_array($route_match->getRouteName(), self::ROUTES)) {
      return;
    }

    $rdf_entity = $route_match->getParameter('rdf_entity');
    if (!$rdf_entity instanceof GroupInterface || !$rdf_entity->access('view')) {
      return;
    }

    $event->setResponse(new RedirectResponse($rdf_entity->toUrl('canonical', ['absolute' => TRUE])->toString()));
  }

}
