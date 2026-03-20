<?php

declare(strict_types=1);

namespace Drupal\joinup_news\Controller;

use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the /news path.
 */
class NewsController {

  /**
   * Redirects /news to the search page, filtered on news articles.
   *
   * Until the News page is ready we temporarily redirect to the search page.
   * This way our users can already find the news they are looking for.
   */
  public function redirect(): Response {
    $url = Url::fromRoute('view.search.page_1');
    $url->setOption('query', ['f' => ['type:news']]);
    return new LocalRedirectResponse($url->toString(), Response::HTTP_FOUND);
  }

}
