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

/**
 * Case diff emails.
 */
function joinup_core_deploy_200101(): void {
  // Query taken from https://www.drupal.org/node/3486109.
  $query = <<<QUERY
    SELECT ufd.uid, ufd.name, ufd.mail, ufd.status
    FROM users_field_data ufd
    JOIN (
      SELECT LOWER(mail) AS lower_mail
      FROM users_field_data
      GROUP BY LOWER(mail)
      HAVING COUNT(uid) > 1
    ) duplicates ON LOWER(ufd.mail) = duplicates.lower_mail
    ORDER BY LOWER(ufd.mail), ufd.uid;
    QUERY;

  $records = \Drupal::database()->query($query)->fetchAll();
  $content = ['UserID, Username, Email, Status'];
  foreach ($records as $record) {
    $content[] = sprintf('%s, "%s", %s, %s', $record->uid, $record->name, $record->mail, $record->status);
  }
  $uri = 'private://duplicates.csv';
  file_put_contents($uri, implode("\n", $content));

  $entityTypeManager = \Drupal::entityTypeManager();

  // Store data in the private file-system wrapped into a message.
  $entityTypeManager->getStorage('message')->create([
    'template' => 'contact_form_submission',
    'field_contact_category' => 'usage',
    'field_contact_subject' => 'Emails varying only by case',
    'field_contact_attachment' => $entityTypeManager->getStorage('file')->create([
      'uri' => $uri,
      'status' => TRUE,
    ]),
  ])->save();
}

/**
 * Clean up db.
 */
function joinup_core_deploy_200102(): void {
  $deletedStorage = \Drupal::service('entity_field.deleted_fields_repository');

  $fieldStorages = [
    'meta_entity.field_ss_related_solutions',
    'message.field_group_content',
  ];
  /** @var \Drupal\field\Entity\FieldStorageConfig $fieldStorageDefinition */
  foreach ($deletedStorage->getFieldStorageDefinitions() as $fieldStorageDefinition) {
    if (in_array($fieldStorageDefinition->id(), $fieldStorages, TRUE)) {
      $deletedStorage->removeFieldStorageDefinition($fieldStorageDefinition);
    }
  }

  $fieldConfigs = [
    'meta_entity.solution_settings.field_ss_related_solutions',
  ];
  /** @var \Drupal\field\Entity\FieldConfig $fieldDefinition */
  foreach ($deletedStorage->getFieldDefinitions() as $fieldConfig) {
    if (in_array($fieldConfig->id(), $fieldConfigs, TRUE)) {
      $deletedStorage->removeFieldDefinition($fieldConfig);
    }
  }

  $database = \Drupal::database();
  $tables = [
    // meta_entity.field_ss_related_solutions
    'field_deleted_data_e581954d71',
    // message.field_group_content
    'field_deleted_data_aa42a47f4e',
  ];
  foreach ($tables as $table) {
    if ($database->schema()->tableExists($table)) {
      $database->schema()->dropTable($table);
    }
  }
}
