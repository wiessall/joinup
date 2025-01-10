<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Entity;

use Drupal\user\UserInterface;

/**
 * Interface for cancelled users.
 */
interface JoinupUserInterface extends UserInterface {

  /**
   * Whether or not the user is a moderator.
   *
   * @return bool
   *   TRUE if the user is a moderator, FALSE otherwise.
   */
  public function isModerator(): bool;

}
