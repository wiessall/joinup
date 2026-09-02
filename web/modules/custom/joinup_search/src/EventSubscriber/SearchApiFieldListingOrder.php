<?php

declare(strict_types=1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\facets\Utility\FacetsDateHandler;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\search_api\Query\QueryInterface as SearchApiQueryInterface;
use Drupal\search_api_solr\Event\PreQueryEvent;
use Solarium\Core\Query\QueryInterface as SolariumApiQueryInterface;
use Solarium\QueryType\Select\Query\Query;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter the order of results for search api field.
 */
readonly class SearchApiFieldListingOrder implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'facets.utility.date_handler')]
    protected FacetsDateHandler $facetDateHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Lower priority to make sure it is performed as the last one.
      PreQueryEvent::class => ['queryAlter', -100],
    ];
  }

  /**
   * Reacts to the pre query event.
   *
   * @param \Drupal\search_api_solr\Event\PreQueryEvent $event
   *   The pre query event.
   */
  public function queryAlter(PreQueryEvent $event): void {
    $searchApiQuery = $event->getSearchApiQuery();
    $solariumApiQuery = $event->getSolariumQuery();

    if (!$this->performAlteration($searchApiQuery, $solariumApiQuery)) {
      return;
    }

    $group = $this->getGroupFromQuery($searchApiQuery);
    if ($group) {
      $this->addPinnedSorting($solariumApiQuery, $group);
    }

    // Sort by score.
    $solariumApiQuery->addSort('score', Query::SORT_DESC);

    // In case the user has filtered by event, first sort by the event date.
    // @todo This will be generic when event calendar will be implemented for
    // the node.listing_content and the paragraph.content_listing instances of
    // the search api field listing.
    if (_joinup_search_search_api_has_condition($searchApiQuery->getConditionGroup(), 'entity_bundle', 'event')) {
      // If the upcoming events are selected, set the order to 'ASC'.
      // @see \Drupal\joinup_search\Plugin\ArbitraryFacet\EventsArbitraryFacet::getFacetDefinition
      $value = $this->facetDateHandler->isoDate((new DrupalDateTime('today'))->getTimestamp());
      $order = _joinup_search_search_api_has_condition($searchApiQuery->getConditionGroup(), 'field_event_date_end', $value, '>=') ? Query::SORT_ASC : Query::SORT_DESC;

      $solariumApiQuery->addSort('ds_field_event_date', $order);
    }
    else {
      $solariumApiQuery->addSort('ds_entity_created', Query::SORT_DESC);
    }
  }

  /**
   * Checks if we want to perform alteration.
   *
   * @param \Drupal\search_api\Query\QueryInterface $searchApiQuery
   *   Search api query.
   * @param \Solarium\Core\Query\QueryInterface $solariumApiQuery
   *   Solarium api query.
   *
   * @return bool
   *   True if we want to perform alteration.
   */
  protected function performAlteration(
    SearchApiQueryInterface $searchApiQuery,
    SolariumApiQueryInterface $solariumApiQuery,
  ): bool {
    if (!($searchApiQuery->hasTag('search_api_field_listing_content') ||
      $searchApiQuery->hasTag('search_api_field_content_listing'))) {
      return FALSE;
    }

    // We already have some sorting, for example from "Content filter".
    if (!empty($solariumApiQuery->getSorts())) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets the group the query is running in.
   *
   * @param \Drupal\search_api\Query\QueryInterface $searchApiQuery
   *   Search API query.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The listing's group, or NULL if there is no group.
   */
  protected function getGroupFromQuery(SearchApiQueryInterface $searchApiQuery): ?GroupInterface {
    $entity = $searchApiQuery->getOption('search_api_field entity');
    while ($entity instanceof ParagraphInterface) {
      $entity = $entity->getParentEntity();
    }

    if (!$entity instanceof SolutionInterface && $entity instanceof GroupContentInterface && $entity->hasGroup()) {
      $entity = $entity->getGroup();
    }

    return $entity instanceof GroupInterface ? $entity : NULL;
  }

  /**
   * Adds pinned sorting.
   *
   * @param \Solarium\Core\Query\QueryInterface $solariumApiQuery
   *   Solarium query.
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group the listing belongs to.
   */
  protected function addPinnedSorting(SolariumApiQueryInterface $solariumApiQuery, GroupInterface $group): void {
    // The subquery matches the group ID in the multi-valued
    // `itm_node_pinned_in` field, so the expression returns 1 for content
    // pinned in this group and 0 for everything else. Sorting on it descending
    // brings the pinned content to the top. The field is kept up to date by the
    // commit that the pin and unpin routes issue.
    $computedSort = sprintf(
      'if(query({!term f=itm_node_pinned_in v=%d},0),1,0)',
      $group->id()
    );
    $solariumApiQuery->addSort($computedSort, Query::SORT_DESC);
  }

}
