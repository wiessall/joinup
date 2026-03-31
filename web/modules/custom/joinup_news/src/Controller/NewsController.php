<?php

declare(strict_types=1);

namespace Drupal\joinup_news\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the /news path.
 */
class NewsController implements ContainerInjectionInterface {

  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('language_manager'),
    );
  }

  /**
   * Redirects /news to the search page, filtered on news articles.
   *
   * Until the News page is ready we temporarily redirect to the search page.
   * This way our users can already find the news they are looking for.
   */
  public function redirect(): Response {
    $url = Url::fromRoute('view.search.page_1');
    $url->setOption('query', ['f' => ['type:news']]);
    $url->setOption('language', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL));
    $response = new LocalRedirectResponse($url->toString(), Response::HTTP_FOUND);
    $response->getCacheableMetadata()->addCacheContexts([
      'languages:language_url',
    ]);
    return $response;
  }

}
