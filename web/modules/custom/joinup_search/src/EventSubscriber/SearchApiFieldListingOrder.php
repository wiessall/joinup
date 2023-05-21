<?php

declare(strict_types = 1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\facets\Utility\FacetsDateHandler;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\QueryInterface as SearchApiQueryInterface;
use Drupal\search_api_solr\Event\PreQueryEvent;
use Solarium\Core\Query\QueryInterface as SolariumApiQueryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter the order of results for search api field.
 */
class SearchApiFieldListingOrder implements EventSubscriberInterface {

  /**
   * Construct subscriber.
   *
   * @param \Drupal\facets\Utility\FacetsDateHandler $facetDateHandler
   *   Facets date handler.
   */
  public function __construct(protected FacetsDateHandler $facetDateHandler) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreQueryEvent::class => ['queryAlter', -200],
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api_solr\Event\PreQueryEvent $event
   *   The query alter event.
   */
  public function queryAlter(PreQueryEvent $event): void {
    $searchApiQuery = $event->getSearchApiQuery();
    if (!$this->performAlteration($searchApiQuery)) {
      return;
    }

    $entity = $this->getEntityFromQuery($searchApiQuery);
    if (!$entity) {
      return;
    }

    $solariumQuery = $event->getSolariumQuery();
    // We already have some sorting, for example from "Content filter".
    if (!empty($solariumQuery->getSorts())) {
      return;
    }

    // Clear all sorts and order by pinned.
    $this->setPinnedSorting($solariumQuery, $entity);

    // Sort by score.
    $solariumQuery->addSort('score', 'desc');

    // In case the user has filtered by event, first sort by the event date.
    // @todo This will be generic when event calendar will be implemented for
    // the rdf_entity.field_is_content and the paragraph.content_listing
    // instances of the search api field listing.
    if (_joinup_search_search_api_has_condition($searchApiQuery->getConditionGroup(), 'entity_bundle', 'event')) {
      // If the upcoming events are selected, set the order to 'ASC'.
      // @see \Drupal\joinup\Plugin\ArbitraryFacet\EventsArbitraryFacet::getFacetDefinition
      $value = $this->facetDateHandler->isoDate((new DrupalDateTime('today'))->getTimestamp());
      $order = _joinup_search_search_api_has_condition($searchApiQuery->getConditionGroup(), 'field_event_date', $value, '>=') ? QueryInterface::SORT_ASC : QueryInterface::SORT_DESC;

      $solariumQuery->addSort('ds_field_event_date', $order);
    }
    else {
      $solariumQuery->addSort('ds_entity_created', 'desc');
    }
  }

  /**
   * Checks if we want to perform alteration.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search query.
   *
   * @return bool
   *   True if we want to perform alteration.
   */
  protected function performAlteration(SearchApiQueryInterface $query): bool {
    if ($query->hasTag('search_api_field_field_collection_content') ||
      $query->hasTag('search_api_field_field_is_content') ||
      $query->hasTag('search_api_field_content_listing')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets rdf entity from query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search query.
   *
   * @return \Drupal\rdf_entity\RdfInterface|null
   *   Rdf entity.
   */
  protected function getEntityFromQuery(SearchApiQueryInterface $query): ?RdfInterface {
    $entity = $query->getOption('search_api_field entity');
    if ($entity instanceof RdfInterface) {
      return $entity;
    }

    if ($entity instanceof ParagraphInterface) {
      $entity = $entity->getParentEntity();
    }

    if ($entity instanceof GroupContentInterface && $entity->hasGroup()) {
      $entity = $entity->getGroup();
    }

    return $entity instanceof RdfInterface ? $entity : NULL;
  }

  /**
   * Sets pinned sorting.
   *
   * @param \Solarium\Core\Query\QueryInterface $solariumQuery
   *   Solarium query.
   * @param \Drupal\rdf_entity\RdfInterface $entity
   *   Rdf entity.
   */
  protected function setPinnedSorting(SolariumApiQueryInterface $solariumQuery, RdfInterface $entity): void {
    // In order to sort by the pinned status of the entity, we need to create a
    // sort function query. The values that are used for sort are evaluated at
    // runtime instead of being retrieved from the index.
    // An entity is pinned when:
    // - for nodes, when they are belong to the current group and the sticky
    //   field is set to true;
    // - for solutions, when they are pinned inside the current collection.
    // The query() Solr function query is a good candidate for doing custom
    // sorts, but it will return the score of the subquery. The score can be
    // different between single results and can range from a small fraction
    // above zero to infinite.
    // Example:
    // @codingStandardsIgnoreStart
    // "or(and(bs_sticky, query({!v='sm_entity_groups:<id>'},0)), query({!v='sm_rdf_pinned_in:<id>'},0))"
    // "query({!v='(bs_sticky:true AND sm_entity_groups:<id>) OR (sm_rdf_pinned_in:<id>)'})"
    // @codingStandardsIgnoreEnd
    // The and() operator will return false when the subquery score is lower
    // than 1, so both of the above will fail the sorting.
    // Solr 6.x has the gt(x,y) function, that will return true if x is greater
    // than y. By using gt(), any positive score can be transformed to true.
    // Example:
    // @codingStandardsIgnoreStart
    // "or(and(bs_sticky, gt(query({!v='sm_entity_groups:<id>'},0),0)), gt(query({!v='sm_rdf_pinned_in:<id>'},0),0))"
    // "gt(query({!v='(bs_sticky:true AND sm_entity_groups:<id>) OR (sm_rdf_pinned_in:<id>)'}),0)"
    // @codingStandardsIgnoreEnd
    // Unfortunately, Solr 5.x doesn't support the gt() function.
    // Both Solr versions support the termfreq() function, which will return the
    // number of occurrences of a term in a field of the document.
    // Alternatively, also the exists() function could be used in conjunction
    // with the query() function, but it looks more expensive.
    // Example:
    // @codingStandardsIgnoreLine
    // "or(and(bs_sticky,exists(query({!v='sm_entity_groups:<id>'},0))),exists(query({!v='sm_rdf_pinned_in:<id>'},0)))"
    // @see https://lucene.apache.org/solr/guide/6_6/function-queries.html#function-queries
    // @see http://archive.apache.org/dist/lucene/solr/ref-guide/apache-solr-ref-guide-5.5.pdf
    $computed_sort = sprintf(
      "or(termfreq(sm_node_pinned_in,%1\$s),termfreq(sm_rdf_pinned_in,%1\$s))",
      $solariumQuery->getHelper()->escapePhrase($entity->id())
    );
    $solariumQuery->setSorts([
      $computed_sort => 'desc',
    ]);
  }

}
