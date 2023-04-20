<?php

declare(strict_types = 1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
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
      SearchApiEvents::QUERY_PRE_EXECUTE => ['queryAlter', -100],
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    $has_status = FALSE;
    $query = $event->getQuery();
    $conditions = &$query->getConditionGroup()->getConditions();
    foreach ($conditions as $key => $condition) {
      if ($condition->hasTag('facet:entity_status')) {
        unset($conditions[$key]);
        $has_status = TRUE;
      }
    };

    if (!$has_status) {
      $query->addCondition('entity_status', 'archived', '<>');
    }
  }

}
