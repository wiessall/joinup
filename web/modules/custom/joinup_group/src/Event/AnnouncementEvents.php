<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Event;

/**
 * Defines events for the message announcements.
 */
final class AnnouncementEvents {

  /**
   * An event that fires when a message announcements is created.
   *
   * @var string
   */
  const ANNOUNCEMENT_CREATE = 'joinup.announcement.create';

  /**
   * An event that fires when a message announcements is updated.
   *
   * @var string
   */
  const ANNOUNCEMENT_UPDATE = 'joinup.announcement.update';

}
