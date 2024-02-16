<?php

declare(strict_types=1);

namespace Drupal\joinup_group\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adjust group content.
 *
 * Alters the Search API queries for the group content on the homepage:
 *  - Only show content related to the collection.
 *  - Do not show the collection itself in the search results.
 *  - Exclude custom pages.
 */
class AdjustGroupContentSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field_field_collection_content' => [
        ['queryAlter'],
      ],
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field_field_is_content' => [
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
    $or = $query->createConditionGroup('OR')
      // Show only content that belongs to this group through
      // the aggregated field. This makes sure that other related entities
      // (such as the collection/solution owner)
      // do not show up as collection/solution content tiles.
      ->addCondition('entity_groups', [$entity->id()], 'IN')
      // Or show content that has been shared on this collection.
      ->addCondition('shared_in', [$entity->id()], 'IN');
    $query->addConditionGroup($or);

    // Remove the collection itself from the results.
    $query->addCondition($entity->getEntityType()
      ->getKey('id'), $entity->id(), '<>');

    // Exclude custom pages and discussion from the group overview.
    $query->addCondition('entity_bundle', [
      'custom_page',
      'discussion',
    ], 'NOT IN');
  }

}
