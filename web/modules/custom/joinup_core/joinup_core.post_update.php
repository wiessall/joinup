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

declare(strict_types = 1);

/**
 * Set the published_at to NULL where it currently is 2147483647.
 */
function joinup_core_post_update_110000(): void {
  $queries = [
    "UPDATE node_field_revision SET published_at = NULL WHERE published_at = 2147483647;",
    "UPDATE node_field_data SET published_at = NULL WHERE published_at = 2147483647;",
  ];
  foreach ($queries as $query) {
    \Drupal::database()->query($query);
  }
}
