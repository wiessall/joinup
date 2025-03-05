<?php

/**
 * @file
 * Deployment functions for Joinup User module.
 */

declare(strict_types=1);

/**
 * Remove cancelled users.
 */
function joinup_user_deploy_200300(?array &$sandbox = NULL): string {
  $storage = \Drupal::entityTypeManager()->getStorage('user');
  $service = \Drupal::getContainer()->get('joinup_user.deleted');

  if (!isset($sandbox['uids'])) {
    $sandbox['uids'] = $storage->getQuery()->accessCheck(FALSE)
      ->condition('status', -1)
      ->execute();
    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['uids']);
  }

  $uids = array_splice($sandbox['uids'], 0, 10);
  $storage->delete($storage->loadMultiple($uids));
  $service->destruct();
  $sandbox['progress'] += count($uids);

  $sandbox['#finished'] = (int) empty($sandbox['uids']);
  return "Deleted {$sandbox['progress']} out of {$sandbox['count']} cancelled users";
}

/**
 * Restore the user status field storage definition.
 */
function joinup_user_deploy_200301(): string {
  $fieldName = 'status';
  $entityTypeId = 'user';

  // Load the entity definition update manager.
  $definitionUpdateManager = \Drupal::entityDefinitionUpdateManager();

  // Get the changes for the entity type and check if the field needs updating.
  $updates = $definitionUpdateManager->getChangeList();
  if (!isset($updates[$entityTypeId]['field_storage_definitions'][$fieldName])) {
    return 'No updates found for the user status field.';
  }

  $entityType = $definitionUpdateManager->getEntityType($entityTypeId);
  $entityFieldManager = \Drupal::getContainer()->get('entity_field.manager');
  $field = $entityFieldManager->getBaseFieldDefinitions($entityType->id())[$fieldName];

  // Get the field storage definition.
  $fieldStorageDefinition = $field->getFieldStorageDefinition();

  // Install the field storage definition.
  $definitionUpdateManager->installFieldStorageDefinition($fieldStorageDefinition->getName(), $entityTypeId, 'user', $fieldStorageDefinition);

  return 'Updated the user status field.';
}
