<?php

declare(strict_types=1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Exclude current custom page from results.
 */
class SearchApiExcludeCurrentCustomPage implements EventSubscriberInterface {

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
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();

    // Because we allow listing of custom pages, if the listing is of a custom
    // page, ensure that the current page is excluded from the results to avoid
    // infinite recursion.
    if ($query->getSearchId() === 'paragraph.content_listing.content_listing') {
      $parentEntity = $query->getOption('search_api_field entity');
      while ($parentEntity && method_exists($parentEntity, 'getParentEntity')) {
        $parentEntity = $parentEntity->getParentEntity();
      }

      if ($parentEntity) {
        $query->addCondition('id', $parentEntity->id(), '<>');
      }
    }
  }

}
