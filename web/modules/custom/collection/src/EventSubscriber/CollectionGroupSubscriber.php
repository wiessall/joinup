<?php

declare(strict_types=1);

namespace Drupal\collection\EventSubscriber;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Event\AddGroupContentEvent;
use Drupal\joinup_group\EventSubscriber\AddGroupContentEventSubscriberBase;

/**
 * Subscribes to Interoperable Europe Portal Group events.
 */
class CollectionGroupSubscriber extends AddGroupContentEventSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function addLinks(AddGroupContentEvent $event): void {
    if ($event->getGroup() instanceof CollectionInterface) {
      parent::addLinks($event);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundles(): array {
    return [
      'node' => [
        'glossary',
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
      'rdf_entity' => $event->getGroup()->id(),
      'node_type' => $bundleId,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    return 40;
  }

}
