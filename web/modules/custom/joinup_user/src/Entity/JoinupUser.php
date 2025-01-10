<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Entity;

use Drupal\user\Entity\User;

/**
 * Wraps the Drupal core user entity class.
 */
class JoinupUser extends User implements JoinupUserInterface {

  /**
   * {@inheritdoc}
   */
  public function isModerator(): bool {
    return $this->hasRole('moderator');
  }

}
