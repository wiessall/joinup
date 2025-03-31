<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Utility\UpdateException;
use Drupal\joinup_core\UpdateHelper;
use Drupal\user\Entity\User;

/**
 * Create shares entity meta entities.
 */
function joinup_core_post_update_200401(array &$sandbox): string {
  $db = \Drupal::database();
  $sourceTable = 'node__field_shared_in';
  $timestamp = \Drupal::time()->getCurrentTime();
  $shareStorage = \Drupal::entityTypeManager()->getStorage('meta_entity');
  $accountSwitcher = \Drupal::service('account_switcher');
  assert($accountSwitcher instanceof AccountSwitcherInterface);
  $serviceAccount = User::load(1);

  if (!isset($sandbox['nids_to_process'])) {
    $sandbox['nids_to_process'] = $db->select($sourceTable, 'n')
      ->distinct()
      ->fields('n', ['entity_id'])
      ->execute()
      ->fetchAllKeyed(0, 0);
    $sandbox['total'] = count($sandbox['nids_to_process']);
    $sandbox['processed'] = 0;
    $sandbox['migrated'] = 0;
  }

  $nidsToProcess = array_splice($sandbox['nids_to_process'], 0, 50);

  foreach ($nidsToProcess as $nid) {
    $dataQuery = $db->select($sourceTable, 'd')
      ->fields('d')
      ->condition('d.entity_id', $nid);
    $dataQuery->join('node_field_data', 'nfd', 'd.revision_id = nfd.vid');
    $dataQuery
      ->fields('nfd', ['changed'])
      ->orderBy('d.delta');

    $data = $dataQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $type = current($data)['bundle'];
    $changed = (int) current($data)['changed'];
    $expireTimestamp = match($type) {
      'event' => UpdateHelper::someMonthsLater(6, $changed),
      'news' => UpdateHelper::someMonthsLater(1, $changed),
      default => NULL,
    };

    if ($expireTimestamp && $expireTimestamp < $timestamp) {
      // This share should not be created.
      continue;
    }

    $shares = array_map(
      function (array $data) use ($expireTimestamp): array {
        return array_filter([
          'target_id' => $data['field_shared_in_target_id'],
          'timestamp' => $expireTimestamp,
        ]);
      },
      $data,
    );

    try {
      $accountSwitcher->switchTo($serviceAccount);
      $share = $shareStorage->create([
        'type' => 'shared_in',
        'target' => [
          'target_id' => $nid,
          'target_type' => 'node',
        ],
        'field_shared_in' => $shares,
      ]);
      assert($share instanceof ContentEntityInterface);

      if ($share->validate()->count()) {
        throw new UpdateException();
      }
      $share->save();
      $sandbox['migrated']++;
    }
    finally {
      $accountSwitcher->switchBack();
    }
  }
  $sandbox['processed'] += count($nidsToProcess);
  $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];

  return sprintf(
    "Processed %s of %s shared content. Migrated %s items, skipped %s shared content (because their sharing already expired).",
    $sandbox['processed'],
    $sandbox['total'],
    $sandbox['migrated'],
    $sandbox['processed'] - $sandbox['migrated'],
  );
}
