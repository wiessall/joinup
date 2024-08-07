<?php

declare(strict_types=1);

namespace Drupal\custom_page\EventSubscriber;

use Drupal\joinup_group\Event\AddGroupContentEvent;
use Drupal\joinup_group\EventSubscriber\AddGroupContentEventSubscriberBase;

/**
 * Subscribes to Joinup Group events.
 */
class CustomPageGroupSubscriber extends AddGroupContentEventSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function getBundles(): array {
    return [
      'node' => [
        'custom_page',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    return 'joinup_group.add_content';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteParameters(AddGroupContentEvent $event, string $entityTypeId, string $bundleId): array {
    return [
      $event->getGroup()->getEntityTypeId() => $event->getGroup()->id(),
      'node_type' => $bundleId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    return 100;
  }

}
