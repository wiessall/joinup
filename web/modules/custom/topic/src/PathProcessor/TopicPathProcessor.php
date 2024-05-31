<?php

declare(strict_types=1);

namespace Drupal\topic\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\topic\Entity\TopicInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Redirects the canonical URIs of topic terms to the search page.
 *
 * Topic pages are planned but not yet ready. Until they are we direct the user
 * to the search page, with the topic pre-filtered.
 */
class TopicPathProcessor implements OutboundPathProcessorInterface {

  /**
   * Route option indicating that the URI should be redirected to the search.
   */
  public const REDIRECT_TO_SEARCH_PAGE_OPTION = 'topic_redirect_to_search';

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    $route = $options['route'] ?? NULL;
    $topic = $options['entity'] ?? NULL;
    if (
      $route instanceof Route
      && $route->getOption(static::REDIRECT_TO_SEARCH_PAGE_OPTION)
      && $topic instanceof TopicInterface
    ) {
      $options['query'] = ['f' => ['topic:' . $topic->id()]];
      return '/search';
    }

    return $path;
  }

}
