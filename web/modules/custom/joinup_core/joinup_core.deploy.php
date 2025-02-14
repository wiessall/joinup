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

use Drupal\Core\Database\Database;

/**
 * Update pathauto state for "RDF" nodes with aliases.
 */
function joinup_core_deploy_200300(array &$sandbox): string {
  $database = Database::getConnection();

  if (!isset($sandbox['ids'])) {
    $query = $database->select('node_field_data', 'n');
    $query->fields('n', ['nid'])
      ->condition('n.type', [
        'collection',
        'solution',
        'distribution',
        'release',
      ], 'IN');
    $query->join('key_value', 'k', "n.nid = k.name AND k.collection = :collection", [':collection' => 'pathauto_state.node']);
    $query->condition('k.value', 'i:0;');

    $sandbox['ids'] = $query->execute()->fetchCol();
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;
  }

  $ids = array_splice($sandbox['ids'], 0, 100);
  if (!empty($ids)) {
    $database->update('key_value')
      ->fields(['value' => 'i:1;'])
      ->condition('name', $ids, 'IN')
      ->condition('collection', 'pathauto_state.node')
      ->execute();
  }

  $sandbox['progress'] += count($ids);
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Processed {$sandbox['progress']} out of {$sandbox['total']}";
}
