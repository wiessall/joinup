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

/**
 * Reschedule all aborted that logged a warning.
 */
function joinup_tmgmt_deploy_201602(array &$sandbox): string {
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

  foreach ($batch as $tjiid) {
    $query = $connection->select('tmgmt_job_item', 'tji');
    $query->innerJoin('tmgmt_job', 'tj', 'tji.tjid = tj.tjid');

    $item = $query
      ->fields('tji', ['plugin', 'item_type', 'item_id', 'data'])
      ->fields('tj', ['target_language'])
      ->condition('tji.tjiid', $tjiid)
      ->condition('tji.state', JobItemInterface::STATE_ABORTED)
      ->execute()
      ->fetchAssoc();

    // The item does not exist or is no longer aborted.
    if (!$item) {
      continue;
    }

    $existsQuery = $connection->select('tmgmt_job_item', 'tji');
    $existsQuery->innerJoin('tmgmt_job', 'tj', 'tji.tjid = tj.tjid');

    $exists = (bool) $existsQuery
      ->fields('tji', ['tjiid'])
      ->condition('tji.plugin', $item['plugin'])
      ->condition('tji.item_type', $item['item_type'])
      ->condition('tji.item_id', $item['item_id'])
      ->condition('tj.target_language', $item['target_language'])
      ->condition('tji.state', JobItemInterface::STATE_ABORTED, '<>')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    // Do not create duplicates.
    if ($exists) {
      continue;
    }

    $data = json_decode($item['data'], TRUE);
    $data['#tmgmt_ec_etranslation']['attempted'] = 1;

    $connection->update('tmgmt_job_item')
      ->fields([
        'state' => JobItemInterface::STATE_INACTIVE,
        'data' => json_encode($data),
      ])
      ->condition('tjiid', $tjiid)
      ->execute();
  }

  $sandbox['processed'] += count($batch);
  $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];

  return sprintf('Processed %d of %d job items.', $sandbox['processed'], $sandbox['total']);
}
