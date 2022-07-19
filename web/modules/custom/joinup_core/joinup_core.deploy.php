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
