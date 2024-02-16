<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\EventSubscriber;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\joinup_search\EventSubscriber\SearchApiStatusSubscriber;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a discussions query when status 'archived' is enabled.
 */
class DiscussionSearchApiStatusSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new DiscussionSearchApiStatusSubscriber object.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facet manager.
   */
  public function __construct(protected DefaultFacetManager $facetManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'searchStatusQueryAlter',
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function searchStatusQueryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    // The facet below is only available on the discussions page.
    if ($query->getSearchId() !== 'views_page:discussions__overview') {
      return;
    }
    SearchApiStatusSubscriber::searchQueryAlter($query);
  }

}
