<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\EventSubscriber;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Event\AddGroupContentEvent;
use Drupal\joinup_group\EventSubscriber\AddGroupContentEventSubscriberBase;

/**
 * Subscribes to Joinup Group events.
 */
class SolutionGroupSubscriber extends AddGroupContentEventSubscriberBase {

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
        'solution',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    return 'joinup_solution.collection_solution.tca';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRouteParameters(AddGroupContentEvent $event, string $entityTypeId, string $bundleId): array {
    return [
      'node' => $event->getGroup()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static function getPriority(): int {
    return 90;
  }

}
