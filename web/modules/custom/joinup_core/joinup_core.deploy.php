<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);
use Drupal\user\Entity\User;

/**
 * Block UID 1 if unblocked.
 */
function joinup_core_deploy_110300(): string {
  $user = User::load(1);
  if ($user->isBlocked()) {
    return 'User 1 is already blocked.';
  }

  $user->block();
  return 'User 1 has been blocked.';
}
