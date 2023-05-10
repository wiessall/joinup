<?php

declare(strict_types = 1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_user\Entity\JoinupUserInterface;
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => ['searchStatusQueryAlter', -100],
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field' => [
        ['groupContentListingQueryAlter'],
        ['userContentListingQueryAlter'],
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

    $this->getConditionGroupInterfaces($query);
  }

  /**
   * Reacts to the query alter event for user content listings.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   */
  public function userContentListingQueryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    $entity = $query->getOption('search_api_field entity');
    if (!($entity instanceof JoinupUserInterface)) {
      return;
    }

    $this->getConditionGroupInterfaces($query);
  }

  /**
   * @param \Drupal\search_api\Query\QueryInterface $query
   *
   * @return void
   */
  protected function getConditionGroupInterfaces(QueryInterface $query): void {
    // Check the `show_archived` flag on the search API field settings in order
    // to not perform any actions if teh flag is set to either 'archived' or
    // 'unarchived'. The facet is only available when both archived and
    // unarchived content is available in order to give the user the ability to
    // further filter the results.
    // @see \_joinup_search_apply_global_search_api_solr_settings
    $value = $query->getOption('search_api_field item')->getValue();
    if (!isset($value['value']['show_archived']) || $value['value']['show_archived'] !== 'all') {
      return;
    }

    // Ensure that the status.
    if (empty($query->getOption('search_api_facets')['entity_status'])) {
      return;
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

    if ($hasStatus) {
      $query->addCondition('entity_status', 'archived');
    }
    else {
      $query->addCondition('entity_status', 'archived', '<>');
    }
  }

}
