<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Event;

/**
 * Interface for events that fire when message announcement have sent state.
 */
interface AnnouncementEventInterface {

  /**
   *
   */
  public function getRecipients(): array;

}
