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
  $storage = \Drupal::service('entity_type.manager')
    ->getStorage('tmgmt_job_item');
  $batch_size = 50;

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = (int) $connection->select('tmgmt_message', 'tm')
      ->fields('tm', ['tjiid'])
      ->condition('message', 'Aborted. No translation received after 6 hours')
      ->distinct()
      ->countQuery()
      ->execute()
      ->fetchField();
    $sandbox['current'] = 0;
  }

  $items = $connection->select('tmgmt_message', 'tm')
    ->fields('tm', ['tjiid'])
    ->condition('message', 'Aborted. No translation received after 6 hours')
    ->distinct()
    ->range($sandbox['current'], $batch_size)
    ->execute()
    ->fetchCol();

  if (empty($items)) {
    $sandbox['#finished'] = 1;
    return 'Completed processing all job items.';
  }

  $jobItems = $storage->loadMultiple($items);

  foreach ($jobItems as $jobItem) {
    $jobItem->setState(JobItemInterface::STATE_ACTIVE);
    $jobItem->updateData(
      ['#tmgmt_ec_etranslation'],
      [
        'attempted' => 1,
      ]
    );
    $jobItem->save();
    $sandbox['current']++;
  }

  $storage->resetCache($items);
  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['current'] / $sandbox['total']);

  return sprintf('Processed %d of %d job items.', $sandbox['current'], $sandbox['total']);
}
