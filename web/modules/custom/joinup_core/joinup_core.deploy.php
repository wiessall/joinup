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
 * Sets nodes to anonymous and fills in the author_realname field.
 */
function joinup_core_deploy_200202(array &$sandbox) {
  if (empty($sandbox['nids'])) {
    $query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('status', -1);
    $uids = $query->execute();

    // Get all nodes with uid 0.
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('uid', $uids, 'IN');

    $sandbox['nids'] = $query->execute();
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['nids']);

    if (empty($sandbox['nids'])) {
      return 'No content were found of cancelled users.';
    }
  }

  // During event updates, there are some, that their location cannot be
  // resolved by the geocoder due to invalid input. This is not part of the
  // deployment, so we disable the geocoder recalculation.
  \Drupal::request()->attributes->set('geocoder_presave_disabled', TRUE);

  $nids = array_splice($sandbox['nids'], 0, 10);
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $nodes = $node_storage->loadMultiple($nids);
  foreach ($nodes as $node) {
    $user = $node->getOwner();
    $node->setOwnerId(0);
    $node->set('author_realname', $user->getDisplayName());
    $node->skip_notification = TRUE;
    $node->save();
  }

  \Drupal::request()->attributes->set('geocoder_presave_disabled', FALSE);
  $sandbox['progress'] += count($nids);
  $sandbox['#finished'] = $sandbox['total'] ? $sandbox['progress'] / $sandbox['total'] : 1;
  return 'Updated ' . $sandbox['progress'] . ' out of ' . $sandbox['total'] . ' nodes of a cancelled user.';
}

/**
 * Delete cancelled users.
 */
function joinup_core_deploy_200203(array &$sandbox): string {
  if (empty($sandbox['uids'])) {
    // Get all users with status -1.
    $query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('status', -1);
    $sandbox['uids'] = $query->execute();
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['uids']);

    if (empty($sandbox['uids'])) {
      return 'No users with status -1 found.';
    }
  }

  $uids = array_splice($sandbox['uids'], 0, 10);
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  $user_storage->delete($user_storage->loadMultiple($uids));

  $sandbox['progress'] += count($uids);
  $sandbox['#finished'] = $sandbox['total'] ? $sandbox['progress'] / $sandbox['total'] : 1;
  return 'Deleted ' . $sandbox['progress'] . ' out of ' . $sandbox['total'] . ' users with status "cancelled".';
}

/**
 * Re-setup the status column in the user field data table.
 */
function joinup_core_deploy_200203(array &$sandbox): string {
  $field_name = 'status';
  $entity_type = 'user';

  // Load the entity definition update manager.
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();

  // Get the changes for the entity type and check if the field needs updating.
  $updates = $definition_update_manager->getChangeList();
  if (!isset($updates[$entity_type]['field_storage_definitions'][$field_name])) {
    return 'No updates found for the user status field.';
  }

  $entity_type = $definition_update_manager->getEntityType($entity_type);
  $entity_field_manager = \Drupal::getContainer()->get('entity_field.manager');
  $field = $entity_field_manager->getBaseFieldDefinitions($entity_type->id())[$field_name];

  // Get the field storage definition.
  $field_storage_definition = $field->getFieldStorageDefinition();

  // Install the field storage definition.
  /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition */
  $definition_update_manager->installFieldStorageDefinition($field_storage_definition->getName(), $entity_type->id(), 'user', $field_storage_definition);

  return 'Updated the user status field.';
}
