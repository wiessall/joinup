<?php

/**
 * @file
 * Install, update, and uninstall functions for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\Core\Utility\UpdateException;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Migrates fields from solution to solution_settings meta entity.
 */
function joinup_solution_post_update_200502(array &$sandbox): string {
  $entity_type_manager = \Drupal::entityTypeManager();
  $node_storage = $entity_type_manager->getStorage('node');
  $nids = $node_storage->getQuery()
    ->condition('type', 'solution')
    ->accessCheck(FALSE)
    ->execute();

  if (!isset($sandbox['nids_to_process'])) {
    $sandbox['nids_to_process'] = $nids;
    $sandbox['total'] = count($sandbox['nids_to_process']);
    $sandbox['processed'] = 0;
    $sandbox['solution_settings_count'] = 0;
  }

  $nids_to_process = array_splice($sandbox['nids_to_process'], 0, 50);

  foreach ($nids_to_process as $nid) {
    $solution = $node_storage->load($nid);

    try {
      assert($solution instanceof SolutionInterface);
      $meta_entity = MetaEntity::loadOrCreate('solution_settings', $solution);
      $moderation = (int) $solution->get('group_moderation')->value;
      $list_related_entities = (int) $solution->get('list_related_entities')->value;
      $meta_entity->set('group_moderation', $moderation);
      $meta_entity->set('list_related_entities', $list_related_entities);
      $meta_entity->set('content_creation', $solution->get('content_creation')->value);
      $meta_entity->set('listing_content', $solution->get('listing_content')
        ->getValue());
      $meta_entity->save();
      $sandbox['solution_settings_count']++;
    }
    catch (\Exception $e) {
      throw new UpdateException(sprintf(
        "Saving the meta entity of node %s has failed.\n%s",
        $nid,
        $e->getMessage(),
      ));
    }
  }

  $sandbox['processed'] += count($nidsToProcess);
  $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];

  return sprintf(
    "Processed %s of %s solution content. Migrated %s solutions so far.",
    $sandbox['processed'],
    $sandbox['total'],
    $sandbox['solution_settings_count'],
  );
}
