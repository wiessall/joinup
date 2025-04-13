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
 * Update message__field_contact_category values.
 */
function joinup_core_post_update_200400(array &$sandbox): string {
  $table = 'message__field_contact_category';
  $mapping = [
    'bug' => 'report',
    'usage' => 'question_feature',
    'feature' => 'question_feature',
    'legal' => 'legal',
    'report' => 'report',
    'eu_login' => 'question_feature',
    'interoperable_europe' => 'community',
    'other' => 'question_feature',
  ];
  $database = \Drupal::database();

  foreach ($mapping as $old_value => $new_value) {
    $query = $database->update($table)
      ->fields(['field_contact_category_value' => $new_value])
      ->condition('field_contact_category_value', $old_value);
    $query->execute();
  }

  return 'Updated message__field_contact_category values.';
}
