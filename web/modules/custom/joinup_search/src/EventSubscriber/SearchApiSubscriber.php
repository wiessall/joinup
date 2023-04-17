<?php

declare(strict_types = 1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\facets\Utility\FacetsDateHandler;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a search query before it gets executed.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * Facets date handler.
   *
   * @var \Drupal\facets\Utility\FacetsDateHandler
   */
  protected FacetsDateHandler $dateHandler;

  /**
   * Construct subscriber.
   *
   * @param \Drupal\facets\Utility\FacetsDateHandler $date_handler
   *   Facets date handler.
   */
  public function __construct(FacetsDateHandler $date_handler) {
    $this->dateHandler = $date_handler;
  }

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
    if ($query->getSearchId() === 'rdf_entity.collection.field_collection_content' || preg_match('/^paragraph\..*\.content_listing$/', $query->getSearchId())) {
      // In case the user has filtered by event, first sort by the event date.
      // @todo This will be generic when event calendar will be implemented for
      // the rdf_entity.field_is_content and the paragraph.content_listing
      // instances of the search api field listing.
      if (_joinup_search_search_api_has_condition($query->getConditionGroup(), 'entity_bundle', 'event')) {
        // If the upcoming events are selected, set the order to 'ASC'.
        // @see \Drupal\joinup\Plugin\ArbitraryFacet\EventsArbitraryFacet::getFacetDefinition
        $value = $this->dateHandler->isoDate((new DrupalDateTime('today'))->getTimestamp());
        $order = _joinup_search_search_api_has_condition($query->getConditionGroup(), 'field_event_date', $value, '>=') ? QueryInterface::SORT_ASC : QueryInterface::SORT_DESC;
        $query->sort('field_event_date', $order);
      }
    }

    // Sort collection content by creation date, the newest first.
    $group_content_search_ids = [
      'rdf_entity.collection.field_collection_content',
      'rdf_entity.solution.field_is_content',
    ];

    if (in_array($query->getSearchId(), $group_content_search_ids) || preg_match('/^paragraph\..*\.content_listing$/', $query->getSearchId())) {
      $query->sort('entity_created', 'DESC');
    }

    // Because we allow listing of custom pages, if the listing is of a custom
    // page, ensure that the current page is excluded from the results to avoid
    // infinite recursion.
    if ($query->getSearchId() === 'paragraph.content_listing.content_listing') {
      $parent_entity = $query->getOption('search_api_field entity');
      while (!$parent_entity instanceof CustomPageInterface) {
        $parent_entity = $parent_entity->getParentEntity();
      }
      $query->addCondition('id', $parent_entity->id(), '<>');
    }
  }

}
