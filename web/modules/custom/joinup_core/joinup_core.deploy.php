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
 * Fix solution nodes having wrong default_langcode after joinup 2.0 migration.
 */
function joinup_core_deploy_200100(): void {
  $database = \Drupal::database();
  $tables = ['node_field_data', 'node_field_revision'];
  foreach ($tables as $table) {
    $database->query("
      UPDATE {{$table}} n
      JOIN (SELECT nid, vid FROM {{$table}} GROUP BY nid, vid HAVING COUNT(*) = 1) AS grouped
      ON n.nid = grouped.nid AND n.vid = grouped.vid
      SET n.default_langcode = 1;
    ");
  }
}
