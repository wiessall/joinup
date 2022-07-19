<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Event;

use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\message\MessageInterface;

/**
 * Wraps a node insertion demo event for event listeners.
 */
class AnnouncementsEvent extends NotificationEvent implements AnnouncementEventInterface {

  /**
   *
   */
  public function getRecipients(): array {
    return $this->entity->get('recipients')->referencedEntities();
  }

  /**
   *
   */
  public function getState(): string {
    return $this->entity->get('state')->value;
  }

  /**
   *
   */
  public function getMessage(): MessageInterface {
    return $this->getEntity();
  }

}
