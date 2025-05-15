<?php

/**
 * @file
 * Install, update, and uninstall functions for the Joinup solution module.
 */

declare(strict_types=1);

use Drupal\Core\Utility\UpdateException;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Migrates fields from collection to collection_settings meta entity.
 */
function collection_post_update_200502(array &$sandbox): string {
  $entity_type_manager = \Drupal::entityTypeManager();
  $node_storage = $entity_type_manager->getStorage('node');
  $nids = $node_storage->getQuery()
    ->condition('type', 'collection')
    ->accessCheck(FALSE)
    ->execute();

  if (!isset($sandbox['nids_to_process'])) {
    $sandbox['nids_to_process'] = $nids;
    $sandbox['total'] = count($sandbox['nids_to_process']);
    $sandbox['processed'] = 0;
    $sandbox['solutionSettingsCount'] = 0;
  }

  $nidsToProcess = array_splice($sandbox['nids_to_process'], 0, 50);

  foreach ($nidsToProcess as $nid) {
    $collection = $node_storage->load($nid);

    try {
      assert($collection instanceof CollectionInterface);
      $meta_entity = MetaEntity::loadOrCreate('collection_settings', $collection);
      $moderation = (int) $collection->get('group_moderation')->value;
      $meta_entity->set('group_moderation', $moderation);
      $meta_entity->save();
      $sandbox['solutionSettingsCount']++;
    }
    catch (\Exception $e) {
      throw new UpdateException(sprintf(
        "Saving the sharing entity of node %s has failed.\n%s",
        $nid,
        $e->getMessage(),
      ));
    }
  }

  $sandbox['processed'] += count($nidsToProcess);
  $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];

  return sprintf(
    "Processed %s of %s collection content. Migrated %s solutions so far.",
    $sandbox['processed'],
    $sandbox['total'],
    $sandbox['solutionSettingsCount'],
  );
}
