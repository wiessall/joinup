<?php

declare(strict_types=1);

namespace Drupal\joinup_news\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for news.
 */
readonly class JoinupNewsEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected InboundPathProcessorInterface $pathProcessor,
    protected LanguageManagerInterface $languageManager,
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
   * Redirects /news to the search page, filtered on news articles.
   *
   * Until the News page is ready we temporarily redirect to the search page.
   * This way our users can already find the news they are looking for.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   */
  public function onKernelRequest(RequestEvent $event): void {
    // Retrieve the request path, and do inbound processing so that language
    // prefixes are removed.
    $request = $event->getRequest();
    if (strpos($request->getPathInfo(), '/system/files/') === 0 && !$request->query->has('file')) {
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

    if ($path === '/news') {
      $url = Url::fromRoute('view.search.page_1');
      $url->setOption('query', ['f' => ['type:news']]);
      $url->setOption('language', $this->languageManager->getCurrentLanguage());
      $response = new LocalRedirectResponse($url->toString(), Response::HTTP_FOUND);
      $event->setResponse($response);
    }
  }

}
