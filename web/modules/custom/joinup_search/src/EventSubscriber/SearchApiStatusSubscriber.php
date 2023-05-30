<?php

declare(strict_types = 1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\ConditionGroupInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a search query when status 'archived' is enabled.
 */
class SearchApiStatusSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => [
        ['searchStatusQueryAlter', -100],
        ['searchAutocompleteQueryAlter', -100],
      ],
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function searchStatusQueryAlter(QueryPreExecuteEvent $event): void {
    $hasStatus = FALSE;
    $query = $event->getQuery();

    if ($query->getSearchId() !== 'views_page:search__page_1') {
      return;
    }

    $conditions = &$query->getConditionGroup()->getConditions();
    foreach ($conditions as $key => $condition) {
      if (($condition instanceof ConditionGroupInterface) && $condition->hasTag('facet:entity_status')) {
        unset($conditions[$key]);
        $hasStatus = TRUE;
      }
    }

    if ($hasStatus) {
      $query->addCondition('entity_status', 'archived');
    }
    else {
      $query->addCondition('entity_status', 'archived', '<>');
    }
  }

  /**
   * Reacts to the autocomplete alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function searchAutocompleteQueryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();

    if ($query->getSearchId() !== 'search_api_autocomplete:search') {
      return;
    }

    $conditions = &$query->getConditionGroup()->getConditions();
    foreach ($conditions as $key => $condition) {
      if (($condition instanceof ConditionGroupInterface) && $condition->hasTag('facet:entity_status')) {
        unset($conditions[$key]);
      }
    }
    $query->addCondition('entity_status', 'archived', '<>');
  }

}
