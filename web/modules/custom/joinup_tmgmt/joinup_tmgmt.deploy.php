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
