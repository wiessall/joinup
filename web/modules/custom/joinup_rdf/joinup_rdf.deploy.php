<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

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
  $endpoint = \Drupal::getContainer()->get('rdf_sync.connection');
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
 * Migrate solutions.
 */
function joinup_rdf_deploy_200002(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('rdf_sync.connection');
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
 * Clean the collection and solution graphs.
 */
function joinup_rdf_deploy_200003(): void {
  $endpoint = \Drupal::getContainer()->get('rdf_sync.connection');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/collection/published>');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/collection/draft>');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/solution/published>');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/solution/draft>');
}

/**
 * Apply entity definition updates.
 */
function joinup_rdf_deploy_200004(): void {
  $entity_update_manager = \Drupal::entityDefinitionUpdateManager();
  if (!$entity_update_manager->needsUpdates()) {
    return;
  }

  $entity_field_manager = \Drupal::getContainer()->get('entity_field.manager');
  $database = \Drupal::database();
  foreach ($entity_update_manager->getChangeList() as $entity_type_id => $changes) {
    $entity_type = $entity_update_manager->getEntityType($entity_type_id);
    if (isset($changes['field_storage_definitions'])) {
      $field_storage_definitions = $entity_field_manager->getFieldStorageDefinitions($entity_type_id);
      foreach ($changes['field_storage_definitions'] as $field_name => $value) {
        /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition */
        $storage_definition = $field_storage_definitions[$field_name];
        $table_name = $entity_type_id . '__' . $field_name;
        $revision_table_name = $entity_type_id . '_revision__' . $field_name;
        $revision_table_exists = $database->schema()
          ->tableExists($revision_table_name);

        // The target ID has already been converted from string to numeric
        // during the migration process. We need to only extract the data so
        // that the field storage definition can be updated.
        $data = $database->select($table_name, 't')
          ->fields('t')
          ->execute()
          ->fetchAllAssoc('revision_id');
        $database->truncate($table_name)->execute();
        if ($revision_table_exists) {
          $revision_data = $database->select($revision_table_name, 't')
            ->fields('t')
            ->execute()
            ->fetchAllAssoc('revision_id');
          $database->truncate($revision_table_name)->execute();
        }

        $entity_update_manager->updateFieldStorageDefinition($storage_definition);
        $fields = [
          'bundle',
          'deleted',
          'entity_id',
          'revision_id',
          'langcode',
          'delta',
          $field_name . '_target_id',
        ];

        if (!empty($data)) {
          $values = [];
          foreach ($data as $row) {
            $values = array_map(fn($field) => $row->$field, $fields);
          }
          $database->insert($table_name)
            ->fields($fields)
            ->values($values)
            ->execute();
        }

        if ($revision_table_exists && !empty($revision_data)) {
          $values = [];
          foreach ($revision_data as $row) {
            $values = array_map(fn($field) => $row->$field, $fields);
          }

          $database->insert($revision_table_name)
            ->fields($fields)
            ->values($values)
            ->execute();
        }
      }
    }
    $entity_update_manager->updateEntityType($entity_type);
  }
}
