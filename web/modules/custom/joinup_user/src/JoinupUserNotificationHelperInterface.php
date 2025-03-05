<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\user\UserInterface;

/**
 * Interface for services that assist with sending notifications.
 */
interface JoinupUserNotificationHelperInterface {

  /**
   * Sends a notification to the user that a moderator changed their account.
   *
   * @param \Drupal\user\UserInterface $user
   *   The affected user.
   */
  public function notifyOnAccountChange(UserInterface $user): void;

}
