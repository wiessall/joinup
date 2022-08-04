<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Event;

/**
 * Interface for events that fire when message announcement have sent state.
 */
interface AnnouncementsEventsInterface {

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

  /**
   * Returns the list of recipients.
   *
   * @return \Drupal\user\UserInterface[]
   *   The list of recipients as an array of user accounts.
   */
  public function getRecipients(): array;

  /**
   * Return the state of announcement.
   *
   * @return string
   *   The announcement current state.
   */
  public function getState(): string;

}
