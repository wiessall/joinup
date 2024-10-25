<?php

/**
 * @file
 * Deploy functions for Portal.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);

/**
 * Remove hard bounced emails (aka “permanent” bounces).
 */
function joinup_core_deploy_200000(array &$sandbox): string {
  if (empty($sandbox['usernames'])) {
    $sandbox['usernames'] = str_getcsv(file_get_contents(__DIR__ . "/fixtures/bounce_username.csv"));
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['usernames']);
  }

  $usernames = array_splice($sandbox['usernames'], 0, 50);

  foreach ($usernames as $username) {
    $user = user_load_by_name($username);
    if ($user) {
      $edit = ['user_cancel_notify' => FALSE];
      // Call the user_cancel to cancel and log.
      user_cancel($edit, $user->id(), 'joinup_user_cancel');
    }
    else {
      \Drupal::logger('joinup_core')->warning('No user found for username: @username', ['@username' => $username]);
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} of users.";
}
