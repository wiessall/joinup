<?php

declare(strict_types = 1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\joinup_group\Entity\GroupInterface;
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
      SearchApiEvents::QUERY_PRE_EXECUTE => ['searchStatusQueryAlter', -100],
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field' => [
        ['groupContentListingQueryAlter'],
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
    $query = $event->getQuery();

    // The facet below is only available on the search page.
    if ($query->getSearchId() !== 'views_page:search__page_1') {
      return;
    }

    $this->applyDefaultStatusFacetBehaviour($query);
  }

  /**
   * Reacts to the query alter event for group content listings.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   */
  public function groupContentListingQueryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    $entity = $query->getOption('search_api_field entity');
    if (!($entity instanceof GroupInterface)) {
      return;
    }

    $this->applyDefaultStatusFacetBehaviour($query);
  }

  /**
   * Apply default status facet behaviour.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search API query.
   */
  protected function applyDefaultStatusFacetBehaviour($query): void {
    $hasStatus = FALSE;
    $conditions = &$query->getConditionGroup()->getConditions();
    foreach ($conditions as $key => $condition) {
      if (($condition instanceof ConditionGroupInterface) && $condition->hasTag('facet:entity_status')) {
        unset($conditions[$key]);
        $hasStatus = TRUE;
      }
    }

    if (!$hasStatus) {
      $query->addCondition('entity_status', 'archived', '<>');
    }
  }

}
