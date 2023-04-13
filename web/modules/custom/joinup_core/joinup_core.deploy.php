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

declare(strict_types = 1);

/**
 * Remove all the blocked users before the spam activity (03/12/2022)
 */
function joinup_core_deploy_0109200(): string {
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  $from_date = new \DateTime("2022/12/03");
  $uids = \Drupal::entityQuery('user')
    ->condition('status', '0')
    ->condition('uid', 1, '>')
    ->condition('created', $from_date->getTimestamp(), '<=')
    ->execute();
  $users = $user_storage->loadMultiple($uids);
  $queue = \Drupal::queue('joinup_removed_users');
  $queue->createQueue();
  $i = 0;
  foreach ($users as $user) {
    $i++;
    $queue->createItem($user->id());
  }
  return "{$i} blocked users has been added to que.";
}
