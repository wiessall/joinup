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

/**
 * Reschedule all aborted that logged a warning.
 */
function joinup_tmgmt_deploy_201602(): string {
  $now = new DateTime();
  $db = \Drupal::database();
  $schema = $db->schema();
  $tempTable = '_tmgmt_temp';
  $identifier = "CONCAT_WS(':', plugin, item_type, item_id, target_language)";

  // We use a temporary table to help later with the main update statement. The
  // sole colum contains a list of non-aborted items represented by an
  // identifier obtained by concatenating the TMGMT source plugin, the item
  // type, the item ID and the target language. Such an identifier helps to
  // avoid rescheduling these job items, later in the main update statement.
  $schema->createTable($tempTable, [
    'fields' => [
      'id' => [
        'type' => 'varchar',
        // Computed as max from $idExpression:
        // - plugin: 7 ('content' is longest)
        // - item_type: 32 (see EntityTypeInterface::ID_MAX_LENGTH)
        // - item_id: 255
        // - target_language: 2
        // - separator: 3.
        'length' => 299,
        'not null' => TRUE,
      ],
    ],
    'primary key' => ['id'],
  ]);
  // Insert all non-aborted job items in the temporary table.
  $select = $db->select('tmgmt_job_item', 'i')
    ->distinct()
    ->condition('i.state', JobItemInterface::STATE_ABORTED, '<>');
  $select->addExpression($identifier, 'id');
  $select->innerJoin('tmgmt_job', 'j', 'i.tjid = j.tjid');
  $db->insert($tempTable)->from($select)->execute();

  $sql = <<<SQL
    SELECT GROUP_CONCAT(i.tjiid ORDER BY i.tjiid DESC)
    FROM {tmgmt_job_item} i
    INNER JOIN {tmgmt_message} m ON i.tjiid = m.tjiid
    WHERE m.message = 'Aborted. No translation received after 6 hours'
    AND i.state = 4
    GROUP BY i.plugin, i.item_type, i.item_id, m.tjid
    HAVING COUNT(*) > 1
    SQL;
  $toDelete = [];
  foreach ($db->query($sql)->fetchCol() as $tjiids) {
    $tjiids = explode(',', $tjiids);
    // Keep the most recent job item.
    array_shift($tjiids);
    $toDelete = [...$toDelete, ...$tjiids];
  }
  if ($toDelete) {
    $db->delete('tmgmt_job_item')->condition('tjiid', $toDelete, 'IN')->execute();
    $db->delete('tmgmt_message')->condition('tjiid', $toDelete, 'IN')->execute();
  }

  // Update the state.
  $sql = <<<SQL
    UPDATE {tmgmt_job_item} i
    INNER JOIN {tmgmt_message} m ON i.tjiid = m.tjiid
    INNER JOIN {tmgmt_job} j ON m.tjid = j.tjid
    -- Left join to exclude existing items in non-aborted states. This is more
    -- efficient than adding an IN() filter.
    LEFT JOIN {{$tempTable}} t ON $identifier = t.id
    SET i.state = :new_state
    WHERE m.message = :message
    AND i.state = :state
    AND t.id IS NULL
    SQL;
  $db->query($sql, [
    ':new_state' => JobItemInterface::STATE_INACTIVE,
    ':message' => 'Aborted. No translation received after 6 hours',
    ':state' => JobItemInterface::STATE_ABORTED,
  ]);

  // Cleanup.
  $schema->dropTable($tempTable);

  return "Took: {$now->diff(new DateTime())->format('%im %ss')}";
}
