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



/**
 * Unify the spatial coverage field.
 */
function joinup_core_post_update_200100(): void {
  $new_field = 'spatial_coverage';
  $old_fields = [
    'field_document_spatial_coverage',
    'field_news_spatial_coverage',
    'field_event_spatial_coverage',
  ];

  $database = \Drupal::database();
  foreach ($old_fields as $old_field) {
    $database->insert("node__{$new_field}")
      ->from($database->select("node__{$old_field}", 'f')->fields('f'))
      ->execute();

    $database->insert("node_revision__{$new_field}")
      ->from($database->select("node_revision__{$old_field}", 'f')->fields('f'))
      ->execute();
  }
}
