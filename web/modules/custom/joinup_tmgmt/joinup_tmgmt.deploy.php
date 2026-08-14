<?php

/**
 * @file
 * Deploy hooks for the Joinup TMGMT module.
 */

declare(strict_types=1);

use Drupal\tmgmt\JobItemInterface;

/**
 * Deletes obsolete continuous job item messages.
 */
function joinup_tmgmt_deploy_201600(array &$sandbox): string {
  $connection = \Drupal::database();
  $message = 'Source was updated, changes were ignored as job item is still active.';
  $limit = 500000;

  $connection->query('DELETE FROM {tmgmt_message} WHERE message = :message LIMIT ' . $limit, [
    ':message' => $message,
  ]);

  $exists = (bool) $connection->select('tmgmt_message', 'tm')
    ->fields('tm', ['mid'])
    ->condition('message', $message)
    ->range(0, 1)
    ->execute()
    ->fetchField();

  $sandbox['#finished'] = $exists ? 0 : 1;

  return $sandbox['#finished'] ? 'Deleted obsolete tmgmt messages.' : 'Deleting obsolete tmgmt messages, batch complete, more remaining.';
}

/**
 * Reschedule all aborted that logged a warning.
 */
function joinup_tmgmt_deploy_201601(array &$sandbox): string {
  $connection = \Drupal::database();
  $batch_size = 500;

  if (!isset($sandbox['total'])) {
    $sandbox['items'] = $connection->select('tmgmt_message', 'tm')
      ->fields('tm', ['tjiid'])
      ->condition('message', 'Aborted. No translation received after 6 hours')
      ->distinct()
      ->execute()
      ->fetchCol();
    $sandbox['total'] = count($sandbox['items']);
    $sandbox['processed'] = 0;
  }

  $batch = array_splice($sandbox['items'], 0, $batch_size);

  $connection->update('tmgmt_job_item')
    ->fields(['state' => JobItemInterface::STATE_INACTIVE])
    ->condition('tjiid', $batch, 'IN')
    ->execute();

  $sandbox['processed'] += count($batch);
  $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];

  return sprintf('Processed %d of %d job items.', $sandbox['processed'], $sandbox['total']);
}
