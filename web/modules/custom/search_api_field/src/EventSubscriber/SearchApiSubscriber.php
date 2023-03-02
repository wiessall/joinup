<?php

declare(strict_types = 1);

namespace Drupal\search_api_field\EventSubscriber;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a search query before it gets executed.
 */
class SearchApiSubscriber implements EventSubscriberInterface {

  /**
   * The Facet Manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected DefaultFacetManager $facetManager;

  /**
   * Constructs SearchApiSubscriber.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager
   *   The facet manager service.
   */
  public function __construct(DefaultFacetManager $facet_manager) {
    $this->facetManager = $facet_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
    ];
  }

  /**
   * Adds the search_api_field facets to the facet query.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    if ($query->getIndex()
      ->getServerInstance()
      ->supportsFeature('search_api_facets')) {
      $facet_source = 'search_api_field:' . $query->getSearchId();
      $this->facetManager->alterQuery($query, $facet_source);
    }
  }

}
