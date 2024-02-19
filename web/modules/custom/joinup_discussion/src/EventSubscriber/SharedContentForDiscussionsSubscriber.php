<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\ConditionGroupInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter a discussions view query.
 */
class SharedContentForDiscussionsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE . '.views_discussions' => 'alterQuery',
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The alter query.
   */
  public function alterQuery(QueryPreExecuteEvent $event): void {
    $query = $event->getQuery();

    $conditions = &$query->getConditionGroup()->getConditions();
    [$delta, $entityId] = $this->findEntityGroupConditionDetails($conditions);

    if ($delta && $entityId) {
      $shared = $query->createConditionGroup('OR');
      // Show only content that belongs to this group through.
      $shared->addCondition('entity_groups', $entityId);
      // Or show content that has been shared on this collection.
      $shared->addCondition('shared_in', [$entityId], 'IN');

      // Overwrite condition.
      $conditions[$delta] = $shared;
    }
  }

  /**
   * Finds entity group condition details.
   *
   * @param array $conditions
   *   Query conditions.
   *
   * @return array|null[]
   *   Condition delta and value.
   */
  protected function findEntityGroupConditionDetails(array $conditions): array {
    foreach ($conditions as $delta => $condition) {
      if (!$condition instanceof ConditionGroupInterface) {
        continue;
      }

      foreach ($condition->getConditions() as $subConditions) {
        if ($subConditions->getField() === 'entity_groups') {
          return [$delta, $subConditions->getValue()];
        }
      }

    }

    return [NULL, NULL];
  }

}
