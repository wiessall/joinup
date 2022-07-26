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
<<<<<<< HEAD
=======

/**
 * Install the "Announcements" field for og_membership.
 */
function joinup_core_update_108300(): void {
  $field_storage_definition = BaseFieldDefinition::create('boolean')
    ->setName('announcements')
    ->setLabel(t('Announcements'))
    ->setDescription(t('If checked, the user will receive group announcements by email.'))
    ->setCardinality(1)
    ->setRequired(FALSE)
    ->setDefaultValue(FALSE);

  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition(
    'announcements',
    'og_membership',
    'joinup_subscription',
    $field_storage_definition
  );
}

/**
 * Set announcements_enabled enable in collections.
 */
function joinup_core_deploy_108301(array &$sandbox): string {
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = \Drupal::entityQuery('meta_entity')->condition('type', 'collection_settings')->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $meta_entity_storage = \Drupal::entityTypeManager()->getStorage('meta_entity');
  $ids = array_splice($sandbox['ids'], 0, 50);
  foreach ($meta_entity_storage->loadMultiple($ids) as $meta_entity) {
    $meta_entity->set('announcements_enabled', TRUE);
    $meta_entity->save();
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Updated {$sandbox['count']} out of {$sandbox['max']} meta-entity settings collection.";
}
>>>>>>> 6adfcc333a (ISAICP-6403: Set announcements enable in existing collections and add test.)
