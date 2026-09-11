<?php

/**
 * @file
 * Deploy hooks for the Joinup TMGMT module.
 */

declare(strict_types=1);

use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;

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

/**
 * Delete old accepted continuous job items and their recorded messages.
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
    $sandbox['#finished'] = 1;
    return 'There are no items to remove.';
  }

  $connection = \Drupal::database();

  // Get list of the job item IDs belonging to the current job ID.
  $jobItemIds = $connection->select('tmgmt_job_item', 'tmi')
    ->fields('tmi', ['tjiid'])
    ->condition('tmi.tjid', $jobId)
    ->condition('tmi.state', JobItemInterface::STATE_ACCEPTED)
    ->condition('tmi.changed', $sandbox['changed_limit'], '<=')
    ->execute()
    ->fetchCol();

  // Delete the job items together with the rows referencing them.
  $msgDeletedCount = 0;
  $jobItemDeletedCount = 0;
  foreach (array_chunk($jobItemIds, 500) as $jobItemIdSubset) {
    $msgDeletedCount += $connection->delete('tmgmt_message')
      ->condition('tjiid', $jobItemIdSubset, 'IN')
      ->execute();
    // Remote mappings are cascaded by JobItem::postDelete(); the entity API is
    // bypassed here, so they have to be removed explicitly.
    $connection->delete('tmgmt_remote')
      ->condition('tjiid', $jobItemIdSubset, 'IN')
      ->execute();
    $jobItemDeletedCount += $connection->delete('tmgmt_job_item')
      ->condition('tjiid', $jobItemIdSubset, 'IN')
      ->execute();
  }
  $sandbox['messages_deleted'] += $msgDeletedCount;
  $sandbox['items_deleted'] += $jobItemDeletedCount;

  $sandbox['#finished'] = (int) empty($sandbox['continuous_job_ids']);
  $processed = $sandbox['jobs_total'] - count($sandbox['continuous_job_ids']);
  $processedCountPadded = strlen("{$sandbox['jobs_total']}")
      |> (fn(int $digitsInTotal): string => str_pad("$processed", $digitsInTotal, ' ', STR_PAD_LEFT));
  $message = sprintf(
    'Cleaned %s / %s continuous jobs. Deleted %s job item(s) and %s message(s) since last message.',
    $processedCountPadded,
    $sandbox['jobs_total'],
    $jobItemDeletedCount,
    $msgDeletedCount,
  );
  $messageTotal = sprintf(
    'Deleted %s accepted TMGMT continuous job items and %s messages in total. Operation took %s sec.',
    $sandbox['items_deleted'],
    $sandbox['messages_deleted'],
    time() - $sandbox['start'],
  );

  return $sandbox['#finished']
    ? implode("\n", [$message, $messageTotal])
    : $message;
}
