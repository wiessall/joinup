<?php

declare(strict_types=1);

namespace Drupal\joinup_search\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Utility\Utility;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alter search_api items before indexing.
 */
class SearchApiIndexingItemsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiEvents::INDEXING_ITEMS => ['onIndexItems'],
    ];
  }

  /**
   * Filters out non-English content from being indexed.
   *
   * @param \Drupal\search_api\Event\IndexingItemsEvent $event
   *   The indexing items event.
   */
  public function onIndexItems(IndexingItemsEvent $event): void {
    $items = $event->getItems();

    foreach ($items as $item_id => $item) {
      [, $raw_id] = Utility::splitCombinedId($item_id);
      [, $langcode] = Utility::splitPropertyPath($raw_id);
      if (!in_array($langcode, ['en', LanguageInterface::LANGCODE_NOT_SPECIFIED], TRUE)) {
        unset($items[$item_id]);
      }
    }

    $event->setItems($items);
  }

}
