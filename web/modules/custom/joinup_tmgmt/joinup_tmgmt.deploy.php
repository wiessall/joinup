<?php

/**
 * @file
 * Deploy hooks for the Joinup TMGMT module.
 */

declare(strict_types=1);

use Drupal\tmgmt\JobInterface;

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
 * Delete approved and aborted continuous job items and their recorded messages.
 */
function joinup_tmgmt_deploy_201601(array &$sandbox): string {
  if (!isset($sandbox['continuous_job_ids'])) {
    $sandbox['continuous_job_ids'] = \Drupal::entityQuery('tmgmt_job')
      ->accessCheck(FALSE)
      ->condition('state', JobInterface::STATE_CONTINUOUS)
      ->execute();
    $sandbox['jobs_total'] = count($sandbox['continuous_job_ids']);
    $sandbox['start'] = time();
    $sandbox['changed_limit'] = $sandbox['start'] - 7 * 24 * 60 * 60;
    $sandbox['items_deleted'] = 0;
    $sandbox['messages_deleted'] = 0;
  }

  $jobId = array_shift($sandbox['continuous_job_ids']);
  if (!$jobId) {
    return 'There are no items to remove.';
  }

  $connection = \Drupal::database();

  // Get list of the job item IDs belonging to the current job ID.
  $jobItemIds = $connection->select('tmgmt_job_item', 'tmi')
    ->fields('tmi', ['tjiid'])
    ->condition('tmi.tjid', $jobId)
    ->condition('tmi.changed', $sandbox['changed_limit'], '<=')
    ->execute()
    ->fetchCol();

  // Delete all messages which belong to the job item IDs.
  $msgDeletedCount = 0;
  while ($jobItemIdSubset = array_splice($jobItemIds, 0, 1000)) {
    $msgDeletedCount += $connection->delete('tmgmt_message')
      ->condition('tjiid', $jobItemIdSubset, 'IN')
      ->execute();
  }
  $sandbox['messages_deleted'] += $msgDeletedCount;

  // Delete the job items.
  $sandbox['items_deleted'] += $jobItemDeletedCount = $connection->delete('tmgmt_job_item')
    ->condition('tjid', $jobId)
    ->condition('changed', $sandbox['changed_limit'], '<=')
    ->execute();

  $sandbox['#finished'] = (int) empty($sandbox['continuous_job_ids']);
  $processed = $sandbox['jobs_total'] - count($sandbox['continuous_job_ids']);
  $processedCountPadded = strlen("{$sandbox['jobs_total']}")
    |> (fn(int $digitsInTotal): string => str_pad("$processed", $digitsInTotal, ' ', STR_PAD_LEFT));
  $message = sprintf(
    'Cleaned %s / %s continuous jobs. Deleted %s job item(s) and %s message(s).',
    $processedCountPadded,
    $sandbox['jobs_total'],
    $jobItemDeletedCount,
    $msgDeletedCount,
  );
  $messageTotal = sprintf(
    'Deleted %s TMGMT job items and %s messages in total. Operation took %s sec.',
    $sandbox['items_deleted'],
    $sandbox['messages_deleted'],
    time() - $sandbox['start'],
  );

  return $sandbox['#finished']
    ? implode("\n", [$message, $messageTotal])
    : $message;
}
