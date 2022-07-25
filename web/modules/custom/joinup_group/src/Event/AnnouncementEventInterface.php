<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Event;

/**
 * Interface for events that fire when message announcement have sent state.
 */
interface AnnouncementEventInterface {

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
