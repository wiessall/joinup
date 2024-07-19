<?php

declare(strict_types=1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a search query when status 'archived' is enabled.
 */
class SearchApiStatusSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new SearchApiStatusSubscriber object.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facet manager.
   */
  public function __construct(protected DefaultFacetManager $facetManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => [
        ['searchStatusQueryAlter', -100],
        ['searchAutocompleteQueryAlter', -100],
        ['groupContentListingQueryAlter', -10],
      ],
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field' => [
        ['paragraphContentListingQueryAlter'],
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

    $this->searchQueryAlter($query);
  }

  /**
   * Reacts to the query alter event for group content listings.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function groupContentListingQueryAlter(QueryPreExecuteEvent $event): void {
    $this->showArchivedStatusSetupQueryAlter($event);

    $query = $event->getQuery();
    if (!($query->getSearchId() !== 'paragraph.content_listing.content_listing')) {
      return;
    }

    $entity = $query->getOption('search_api_field entity');
    if ($entity instanceof GroupInterface && $entity->isArchived()) {
      return;
    }

    $this->getConditionGroupInterfaces($query);
  }

  /**
   * Reacts to the query alter event for paragraph content listings.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function paragraphContentListingQueryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    $entity = $query->getOption('search_api_field entity');
    if (!($entity instanceof ParagraphInterface)) {
      return;
    }

    $this->getConditionGroupInterfaces($query);
  }

  /**
   * Add archive condition in query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   */
  protected function getConditionGroupInterfaces(QueryInterface $query): void {
    // Check the `show_archived` flag on the search API field settings in order
    // to not perform any actions if teh flag is set to either 'archived' or
    // 'unarchived'. The facet is only available when both archived and
    // unarchived content is available in order to give the user the ability to
    // further filter the results.
    // @see \_joinup_search_apply_global_search_api_solr_settings
    if (empty($query->getOption('search_api_field item'))) {
      return;
    }

    $value = $query->getOption('search_api_field item')->getValue();
    if (!isset($value['value']['show_archived']) || $value['value']['show_archived'] !== 'all') {
      return;
    }

    if (empty($query->getOption('status_facet_name'))) {
      // That means that the status switch is not set in the page, so ignore
      // its effect.
      return;
    }

    if (!$query->getOption('show_archived')) {
      throw new \Exception('The show_archived option should be set. ::showArchivedStatusSetupQueryAlter should run first.');
    }

    $hasStatus = FALSE;
    $conditions = &$query->getConditionGroup()->getConditions();
    foreach ($conditions as $condition) {
      if (($condition instanceof ConditionGroupInterface) && $condition->hasTag('facet:entity_status')) {
        // If the facet is active, it appears in the conditions due to its only
        // state.
        $hasStatus = TRUE;
      }
    }

    if (!$hasStatus && !$query->getOption('search_api_field archived')) {
      $query->addCondition('entity_status', 'archived', '<>');
    }
  }

  /**
   * Sets up the show_archived flag and its related facets as options.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  protected function showArchivedStatusSetupQueryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    /** @var \Drupal\Core\Field\FieldItemInterface $item */
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    if (!($item = $query->getOption('search_api_field item')) || !($entity = $query->getOption('search_api_field entity'))) {
      return;
    }

    $value = $item->getValue()['value'];
    $showArchived = $value['show_archived'] ?? 'all';
    $query->setOption('show_archived', $showArchived);
    if ($archived = $query->getOption('search_api_field archived')) {
      $query->addCondition('entity_status', 'archived');
    }
    if ($showArchived !== 'all' && !$archived) {
      $query->addCondition('entity_status', 'archived', $showArchived === 'archived' ? '=' : '<>');
    }
    else {
      $facets = $this->getEntityStatusFacets($query);
      if (empty($facets)) {
        return;
      }

      if (count($facets) > 1) {
        throw new \Exception('There should be only one facet for the search_api_field facet source.');
      }
      $facet = reset($facets);
      $value = $item->getValue()['value']['fields'];
      if (isset($value[$facet->id()]) && $value[$facet->id()]['region'] !== 'hidden') {
        $query->setOption('status_facet_name', $facet->id());
      }
    }
  }

  /**
   * Returns facets for the status field given a search_api_field query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search_api_field query.
   *
   * @return \Drupal\facets\FacetInterface[]
   *   The facets.
   */
  protected function getEntityStatusFacets(QueryInterface $query): array {
    $entity = $query->getOption('search_api_field entity');
    $item = $query->getOption('search_api_field item');
    $facetSourceId = 'search_api_field:' . $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $item->getParent()->getName();

    $facets = $this->facetManager->getFacetsByFacetSourceId($facetSourceId);
    return array_filter($facets, function (FacetInterface $facet) {
      return $facet->getFieldIdentifier() === 'entity_status';
    });
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

  /**
   * Alter queries with 'entity_status' field.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search_api_field query.
   */
  public static function searchQueryAlter(QueryInterface $query): void {
    $hasStatus = FALSE;
    $conditions = &$query->getConditionGroup()->getConditions();
    foreach ($conditions as $key => $condition) {
      if (($condition instanceof ConditionGroupInterface) && $condition->hasTag('facet:entity_status')) {
        unset($conditions[$key]);
        $hasStatus = TRUE;
        break;
      }
    }

    if ($hasStatus) {
      $query->addCondition('entity_status', 'archived');
    }
    else {
      $query->addCondition('entity_status', 'archived', '<>');
    }
  }

}
