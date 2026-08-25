<?php

/**
 * @file
 * Deploy hooks for the Joinup TMGMT module.
 */

declare(strict_types=1);

/**
 * Deletes obsolete continuous job item messages.
 */
function joinup_tmgmt_deploy_201600(): string {
  $connection = \Drupal::database();
  $message = 'Source was updated, changes were ignored as job item is still active.';

  $count_before = (int) $connection->query('SELECT COUNT(*) FROM {tmgmt_message}')->fetchField();
  $connection->query('DROP TABLE IF EXISTS {tmgmt_message_new}');
  $connection->query('CREATE TABLE {tmgmt_message_new} LIKE {tmgmt_message}');
  $connection->query('INSERT INTO {tmgmt_message_new} SELECT * FROM {tmgmt_message} WHERE [message] <> :message OR [message] IS NULL', [':message' => $message]);
  $connection->query('RENAME TABLE {tmgmt_message} TO {tmgmt_message_old}, {tmgmt_message_new} TO {tmgmt_message}');
  $connection->query('DROP TABLE {tmgmt_message_old}');
  $count_after = (int) $connection->query('SELECT COUNT(*) FROM {tmgmt_message}')->fetchField();

  return sprintf('Deleted %d obsolete tmgmt messages.', $count_before - $count_after);
}
