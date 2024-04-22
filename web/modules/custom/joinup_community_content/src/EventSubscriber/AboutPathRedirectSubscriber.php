<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Redirect subscriber for obsolete paths ending in "/about".
 */
class AboutPathRedirectSubscriber extends HttpExceptionSubscriberBase {

  /**
   * Redirects to base path if path ends in "/about" and base has a redirect.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function on404(ExceptionEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $pathInfo = $event->getRequest()->getPathInfo();
    if (!str_ends_with($pathInfo, '/about')) {
      return;
    }

    $pathWithoutSuffix = preg_replace('#/about$#', '', $pathInfo);
    if (!$pathWithoutSuffix) {
      return;
    }

    $event->setResponse(new TrustedRedirectResponse(
      $pathWithoutSuffix,
      307
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    return ['html'];
  }

}
