<?php

/**
 * @file
 * Install, update, and uninstall functions for the Joinup user module.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;
use Drupal\user\Entity\User;

/**
 * Fix invalid emails containing HTML entities for specific accounts.
 */
function joinup_user_post_update_200600(): void {
  /** @var \Drupal\user\Entity\User[] $users */
  $users = User::loadMultiple([745683, 750289]);
  foreach ($users as $user) {
    $decoded_email = Html::decodeEntities($user->getEmail());
    if ($user->getEmail() !== $decoded_email) {
      $user->setEmail($decoded_email)->save();
    }
  }
}
