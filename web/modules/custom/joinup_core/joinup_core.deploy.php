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

use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Set announcements_enabled enable in collections/solutions.
 */
function joinup_core_deploy_108202(): string {
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = \Drupal::entityQuery('rdf_entity')->condition('rid', [
      'collection',
      'solution',
    ], 'IN')->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $entity_storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  $nids = array_splice($sandbox['ids'], 0, 50);
  foreach ($entity_storage->loadMultiple($nids) as $entity) {
    $meta_entity_name = $entity->bundle() === 'collection' ? 'collection_settings' : 'solution_settings';
    $meta_entity = MetaEntity::loadOrCreate($meta_entity_name, $entity);
    $meta_entity->set('announcements_enabled', TRUE);
    $meta_entity->save();
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Updated {$sandbox['count']} out of {$sandbox['max']} meta-entity settings collection/solution.";
}
