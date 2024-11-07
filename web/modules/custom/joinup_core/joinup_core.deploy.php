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

declare(strict_types=1);

/**
 * Update all solution with default_langcode = 0 and set a new revision.
 */
function joinup_core_deploy_200100(): void {
  $database = \Drupal::database();
  $database->update('node_field_data')
    ->fields(['default_langcode' => 1])
    ->condition('langcode', 'en')
    ->condition('default_langcode', 0)
    ->execute();

  $database->update('node_field_revision')
    ->fields(['default_langcode' => 1])
    ->condition('langcode', 'en')
    ->condition('default_langcode', 0)
    ->execute();
}
