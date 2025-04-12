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
 * Update message__field_contact_category values.
 */
function joinup_core_deploy_200400(array &$sandbox): string {
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
