<?php

declare(strict_types=1);

namespace Drupal\search_api_field\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\facets\Event\GetFacetCacheMaxAge;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API field event subscriber.
 */
class MaxAgeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      GetFacetCacheMaxAge::class => ['searchApiFieldMaxAge'],
    ];
  }

  /**
   * Set the max age for search api field facets.
   *
   * By default, facet sources are uncacheable. However, since the search API
   * field facet source is a field facet source, it is cacheable.
   *
   * @param \Drupal\facets\Event\GetFacetCacheMaxAge $event
   *   The event.
   */
  public function searchApiFieldMaxAge(GetFacetCacheMaxAge $event): void {
    $facet = $event->getFacet();
    if (str_starts_with($facet->getFacetSourceId(), 'search_api_field:')) {
      $event->setCacheMaxAge(Cache::PERMANENT);
    }
  }

}
