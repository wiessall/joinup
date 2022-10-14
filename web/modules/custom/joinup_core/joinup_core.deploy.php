<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_0108603(): void {
  /** @var \Drupal\joinup_core\VideoFieldMigrate $videoEmbedMigrate */
  $videoEmbedMigrate = \Drupal::service('video_field_migrate.migrate');

  $refFieldName = 'video';
  $fields = $videoEmbedMigrate->findFieldsToMigrate();
  foreach ($fields as $index => $field) {
    $values = $videoEmbedMigrate->getFieldValues($field['field_name'], $field['entity_type'], $field['bundle']);
    $fields[$index]['values'] = $values;
  }

  $fieldsToMigrate = [];
  foreach ($fields as $field) {
    foreach ($field['values'] as $id => $value) {
      $mediaType = $videoEmbedMigrate->getRemoteVideoMediaType(strpos($value, 'www.youtube.com') ? 'oembed:remote_videos' : 'media_avportal_video');
      $fieldsToMigrate[] = [
        'field_name' => $field['field_name'],
        'entity_type' => $field['entity_type'],
        'bundle' => $field['bundle'],
        'target_id' => $id,
        'media_type' => $mediaType,
      ];
    }
  }
  foreach ($fieldsToMigrate as $field) {
    $videoEmbedMigrate->migrateField($field['media_type'], $refFieldName, $field['entity_type'], $field['target_id'], $field['field_name']);
  }
}
