<?php

/**
 * @file
 * Deploy hooks for the Joinup TMGMT module.
 */

declare(strict_types=1);

/**
 * Deletes obsolete continuous job item messages.
 */
function joinup_tmgmt_deploy_201500(): string {
  \Drupal::database()->delete('tmgmt_message')
    ->condition('message', 'Source was updated, changes were ignored as job item is still active.')
    ->execute();

  return 'Deleted obsolete tmgmt messages.';
}
