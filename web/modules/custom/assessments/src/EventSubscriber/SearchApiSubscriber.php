<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API events subscriber.
 */
readonly class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'searchQueryAlter',
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function searchQueryAlter(QueryPreExecuteEvent $event): void {
    // Exclude assessments except for the global search page.
    $query = $event->getQuery();
    if ($query->getSearchId() === 'views_page:search__page_1') {
      return;
    }

    $condition_group = $query->createAndAddConditionGroup('AND', ['assessment_filter']);
    $condition_group->addCondition('search_api_datasource', 'entity:assessment', '<>');
  }

}
