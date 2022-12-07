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

use Drupal\pathauto\PathautoState;

/**
 * Fix Interoperable Europe news creation link.
 */
function joinup_core_deploy_108900(): void {
  $entityTypeManager = \Drupal::entityTypeManager();
  $entityTypeManager->getStorage('node')->load(704825)->set('path', [
    'pathauto' => PathautoState::SKIP,
    'alias' => '/interoperable-europe/latest-news',
  ])->save();
  if ($alias = $entityTypeManager->getStorage('path_alias')->load(84170)) {
    $alias->delete();
  }
}

/**
 * Cleanup the spam files from the last 7 days.
 */
function joinup_core_deploy_108901(array &$sandbox = []): string {
  if (empty($sandbox['file_ids'])) {
    // Get files of blocked users from past week.
    $query = \Drupal::database()->select('file_managed', 'f');
    $query->join('users_field_data', 'u', 'f.uid = u.uid');
    $query->fields('f', ['fid']);
    $query->condition('u.status', 0);
    $query->condition('u.created', strtotime('-7 days'), '>=');
    $sandbox['file_ids'] = $query->execute()->fetchCol();

    $sandbox['max'] = count($sandbox['file_ids']);
    $sandbox['current'] = 0;
  }

  $batch_size = 50;
  $file_ids = array_splice($sandbox['file_ids'], 0, $batch_size);
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $file_url_generator = \Drupal::getContainer()->get('file_url_generator');
  foreach ($file_storage->loadMultiple($file_ids) as $file) {
    $file->delete();

    // Ensure that the actual file does not exist.
    $file_uri = $file->getFileUri();
    $file_url_generator->generateAbsoluteString($file_uri);
    if (file_exists($file_uri)) {
      unlink($file_uri);
    }
  }

  $sandbox['current'] += count($file_ids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['max'];

  return "Removed {$sandbox['current']} of {$sandbox['max']} files.";
}
