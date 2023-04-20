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
 * Adding blocked users before the spam activity (03/12/2022) to queue.
 */
function joinup_core_deploy_109200(array &$sandbox = []): string {
  if (empty($sandbox['ids'])) {
    $from_date = new \DateTime("2022/12/03");
    $users_ids = \Drupal::entityQuery('user')
      ->condition('status', '0')
      ->condition('uid', 1, '>')
      ->condition('created', $from_date->getTimestamp(), '<=')
      ->execute();
    $sandbox['ids'] = array_values($users_ids);
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $ids = array_splice($sandbox['ids'], 0, 10);
  $queue = \Drupal::queue('joinup_removed_users');
  $queue->createQueue();
  $queue->createItem($ids);

  for ($i = 0; $i < count($ids); $i++) {
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Adding {$sandbox['count']} to queue {$sandbox['max']} users.";
}
