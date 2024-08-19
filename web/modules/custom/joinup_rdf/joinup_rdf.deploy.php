<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\search_api\Entity\Index;

/**
 * Disable RDF Sync for the deployment.
 */
function joinup_rdf_deploy_200000(): void {
  \Drupal::getContainer()->get('rdf_sync.synchronizer')->disableSynchronization();
}

/**
 * Migrate collections.
 */
function joinup_rdf_deploy_200001(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['collections'])) {
    $query = <<<SPARQL
      SELECT DISTINCT ?id
      FROM <http://joinup.eu/collection/published>
      FROM <http://joinup.eu/collection/draft>
      WHERE {
        ?id a ?type .
      }
      SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['collections'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['collections']);
  }

  $uris = array_splice($sandbox['collections'], 0, 50);
  $migration_helper = \Drupal::getContainer()->get('joinup_rdf.migration_helper');
  $start = microtime(TRUE);
  foreach ($uris as $uri) {
    $migration_helper->migrateCollection($uri);
    $sandbox['progress']++;
  }

  $finish = microtime(TRUE);
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} in " . round($finish - $start, 2) . 's';
}

/**
 * Clean up the search index from old collections.
 */
function joinup_rdf_deploy_200002(): string {
  /** @var \Drupal\search_api\Entity\Index $index */
  $index = Index::load('published');
  $results = $index->query()->addCondition('entity_bundle', 'collection')->execute();
  $ids = array_keys($results->getResultItems());
  // ::trackItems{action} re-constructs the IDs so we need to remove the prefix
  // `entity:rdf_entity:` from the IDs.
  $ids = array_map(function (string $id): string {
    return str_replace('entity:rdf_entity/', '', $id);
  }, $ids);
  $index->trackItemsDeleted('entity:rdf_entity', $ids);

  return 'Collections have been removed from the search index.';
}

/**
 * Migrate solutions.
 */
function joinup_rdf_deploy_200003(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['solutions'])) {
    $query = <<<SPARQL
      SELECT DISTINCT ?id
      FROM <http://joinup.eu/solution/published>
      FROM <http://joinup.eu/solution/draft>
      WHERE {
        ?id a ?type .
      }
      SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['solutions'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['solutions']);
  }

  $uris = array_splice($sandbox['solutions'], 0, 50);
  $migration_helper = \Drupal::getContainer()->get('joinup_rdf.migration_helper');
  $start = microtime(TRUE);
  foreach ($uris as $uri) {
    $migration_helper->migrateSolution($uri);
    $sandbox['progress']++;
  }

  $finish = microtime(TRUE);
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} in " . round($finish - $start, 2) . 's';
}

/**
 * Clean up the search index from old solutions.
 */
function joinup_rdf_deploy_200004(): string {
  /** @var \Drupal\search_api\Entity\Index $index */
  $index = Index::load('published');
  $results = $index->query()->addCondition('entity_bundle', 'solution')->execute();
  $ids = array_keys($results->getResultItems());
  // ::trackItems{action} re-constructs the IDs so we need to remove the prefix
  // `entity:rdf_entity:` from the IDs.
  $ids = array_map(function (string $id): string {
    return str_replace('entity:rdf_entity/', '', $id);
  }, $ids);
  $index->trackItemsDeleted('entity:rdf_entity', $ids);

  return 'Solutions have been removed from the search index.';
}

/**
 * Clean the collection and solution graphs.
 */
function joinup_rdf_deploy_200005(): void {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  $endpoint->query('CLEAR GRAPH <http://joinup.eu/collection/published>');
  $endpoint->query('CLEAR GRAPH <http://joinup.eu/collection/draft>');
  $endpoint->query('CLEAR GRAPH <http://joinup.eu/solution/published>');
  $endpoint->query('CLEAR GRAPH <http://joinup.eu/solution/draft>');
}

/**
 * Flatten RDF values.
 *
 * @param \stdClass[] $data
 *   The RDF data.
 *
 * @return array
 *   The flattened values.
 */
function _joinup_rdf_deploy_flatten_rdf_values(array $data): array {
  $values = [];
  foreach ($data as $row) {
    $property = $row->property->getUri();
    $value = method_exists($row->value, 'getUri') ? $row->value->getUri() : $row->value->getValue();
    $values[$property][] = $value;
  }
  return $values;
}

/**
 * Update the field target ID.
 *
 * @param string $base_table_prefix
 *   The base table prefix without the trailing underscores.
 * @param string $table
 *   The table name.
 * @param string $field
 *   The field name.
 * @param string $old_target_id
 *   The old target ID.
 * @param string $new_target_id
 *   The new target ID.
 * @param bool $include_revisions
 *   Whether to include revisions.
 *
 * @todo Remove this only after the RDF2Node is complete.
 */
function _joinup_rdf_deploy_update_field_target_id(string $base_table_prefix, string $table, string $field, string $old_target_id, string $new_target_id, bool $include_revisions = TRUE): void {
  $connection = \Drupal::database();
  $connection->update($base_table_prefix . '__' . $table)
    ->fields([$field => $new_target_id])
    ->condition($field, $old_target_id)
    ->execute();

  if ($include_revisions) {
    $connection->update($base_table_prefix . '_revision__' . $table)
      ->fields([$field => $new_target_id])
      ->condition($field, $old_target_id)
      ->execute();
  }
}
