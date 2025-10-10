<?php

/**
 * @file
 * Post update functions for the Joinup RDF module.
 */

declare(strict_types=1);

/**
 * Remove aliases with "rdf_entity".
 */
function joinup_rdf_post_update_200700(): string {
  $connection = \Drupal::database();
  $query = $connection->select('path_alias', 'pa')
    ->fields('pa', ['id'])
    ->condition('path', '%/rdf_entity/%', 'LIKE');
  $ids = $query->execute()->fetchCol();

  if ($ids) {
    $schema = $connection->schema();
    if ($schema->tableExists('path_alias_revision')) {
      $connection->delete('path_alias_revision')
        ->condition('id', $ids, 'IN')
        ->execute();
    }
  }

  $connection->delete('path_alias')
    ->condition('id', $ids, 'IN')
    ->execute();

  return 'All path_alias entries containing "rdf_entity" have been removed.';
}
