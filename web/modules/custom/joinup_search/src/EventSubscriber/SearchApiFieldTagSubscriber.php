<?php

declare(strict_types=1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a search query with a 'search_api_field' tag before it gets executed.
 */
class SearchApiFieldTagSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.search_api_field' => [
        ['communityContentParagraphQueryAlter', -100],
      ],
    ];
  }

  /**
   * Reacts to the query alter event for listings in community content.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   */
  public function communityContentParagraphQueryAlter(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();
    $entity = $query->getOption('search_api_field entity');
    $parent_entity = $entity;
    if ($parent_entity instanceof GroupInterface) {
      return;
    }

    // Support nested paragraphs.
    while ($parent_entity instanceof ParagraphInterface) {
      $parent_entity = $parent_entity->getParentEntity();
    }

    $group = $entity;
    if ($parent_entity instanceof GroupContentInterface && $parent_entity->hasGroup()) {
      $group = $parent_entity->getGroup();
    }

    if (!($group instanceof GroupInterface)) {
      return;
    }

    $field_value = $query->getOption('search_api_field item')->value;
    /** @var \Drupal\search_api\Query\Query $query */
    if (empty($field_value['global_search'])) {
      if (!empty($field_value['show_shared'])) {
        $or = $query
          ->createConditionGroup('or')
          ->addCondition('shared_in', [$group->id()], 'IN')
          ->addCondition('entity_groups', [$group->id()], 'IN');
        $query->addConditionGroup($or);
      }
      else {
        // Show only content belonging to the same group.
        $query->addCondition('entity_groups', [$group->id()], 'IN');
      }
    }

    // Remove all results if no presets or content filter.
    if (empty($field_value['query_presets']) && !array_key_exists('query_builder', $field_value)) {
      $query->addCondition($parent_entity->getEntityType()
        ->getKey('id'), $parent_entity->id());
    }

    $query->addCondition($parent_entity->getEntityType()
      ->getKey('id'), $parent_entity->id(), '<>');
  }

}
