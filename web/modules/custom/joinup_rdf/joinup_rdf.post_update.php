<?php

/**
 * @file
 * Post update functions for the Joinup RDF module.
 */

declare(strict_types=1);

/**
 * Remove aliases with "rdf_entity".
 */
function joinup_rdf_post_update_200700(&$sandbox): string {
  $connection = \Drupal::database();

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = (int) $connection->select('path_alias', 'pa')
      ->condition('path', '%rdf_entity%', 'LIKE')
      ->countQuery()
      ->execute()
      ->fetchField();

    $sandbox['current'] = 0;
  }

  if (empty($sandbox['total'])) {
    return 'No path_alias entries containing "rdf_entity" found.';
  }

  $limit = 50;
  $query = $connection->select('path_alias', 'pa')
    ->fields('pa', ['id'])
    ->condition('path', '%rdf_entity%', 'LIKE')
    ->range(0, $limit);
  $ids = $query->execute()->fetchCol();

  if ($ids) {
    $schema = $connection->schema();
    if ($schema->tableExists('path_alias_revision')) {
      $connection->delete('path_alias_revision')
        ->condition('id', $ids, 'IN')
        ->execute();
    }

    $connection->delete('path_alias')
      ->condition('id', $ids, 'IN')
      ->execute();

    $sandbox['current'] += count($ids);
  }

  $sandbox['#finished'] = $sandbox['total'] > 0
    ? ($sandbox['current'] / $sandbox['total'])
    : 1;

  if ($sandbox['#finished'] >= 1) {
    return 'All path_alias entries containing "rdf_entity" have been removed.';
  }
}
