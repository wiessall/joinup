<?php

declare(strict_types=1);

namespace Drupal\joinup_event\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a search query with a 'views_content_overview' tag.
 */
class UpcomingEventsSortOverwriteSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.views_content_overview' => [
        ['queryAlter'],
      ],
    ];
  }

  /**
   * Reacts to the query alter.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();

    $facets = _joinup_get_facets_by_facet_source_id('search_api:views_page__content_overview__page_1');

    // No further processing is needed if we are not filtering on events.
    if (!isset($facets['content_bundle']) || !$facets['content_bundle']->isActiveValue('event')) {
      return;
    }

    $sorts = &$query->getSorts();
    // When filtering for upcoming events, show first the events that are going
    // to happen sooner.
    $order = isset($facets['event_date']) && $facets['event_date']->isActiveValue('upcoming_events') ? QueryInterface::SORT_ASC : QueryInterface::SORT_DESC;
    $sorts = ['field_event_date' => $order] + $sorts;
  }

}
