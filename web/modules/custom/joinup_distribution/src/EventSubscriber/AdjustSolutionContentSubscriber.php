<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\EventSubscriber;

use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adjust solution content.
 */
class AdjustSolutionContentSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field_listing_content' => [
        ['queryAlter'],
      ],
    ];
  }

  /**
   * Reacts to the query alter.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $query->getOption('search_api_field entity');

    if (!$entity instanceof SolutionInterface) {
      return;
    }

    // If we don't have any releases associated to this solution, it also means
    // that any distribution available is directly associated. We don't need any
    // further filtering on the results.
    /** @var \Drupal\joinup_solution\Entity\SolutionInterface $entity */
    if (empty($entity->getReleaseIds())) {
      return;
    }

    // Get the ids of the distributions directly associated to the solution.
    $distributions = $entity->getDistributionIds();

    if (!empty($distributions)) {
      // We need to filter out all the distributions except the ones that are
      // directly associated to the solution.
      $or = $query->createConditionGroup('OR');
      // The next line might seem crazy, but I am not drunk. It's needed to
      // bypass Solr problems with negated queries. To fix this, we need to use
      // the '(*:* NOT entity_bundle:"distribution")' format. Unfortunately,
      // SearchApiSolrBackend::createFilterQuery() uses that syntax only when
      // multiple values are provided. While ugly, nothing is really wrong about
      // passing twice the same bundle.
      // @see \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend::createFilterQuery()
      // @see http://stackoverflow.com/a/635109
      $or->addCondition('entity_bundle', ['distribution', 'distribution'], 'NOT IN');
      $or->addCondition('nid', $distributions, 'IN');
      $query->addConditionGroup($or);
    }
    else {
      // Exclude all the distributions.
      $query->addCondition('entity_bundle', 'distribution', '<>');
    }
  }

}
