<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Event;

use Drupal\joinup_notification\Event\NotificationEvent;

/**
 * Wraps a node insertion demo event for event listeners.
 */
class AnnouncementsEvent extends NotificationEvent implements AnnouncementsEventsInterface {

  /**
   * {@inheritdoc}
   */
  public function getRecipients(): array {
    return $this->entity->get('recipients')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): string {
    return $this->entity->get('state')->value;
  }

}
