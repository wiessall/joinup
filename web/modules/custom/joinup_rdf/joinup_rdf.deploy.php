<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\joinup_rdf\MigrationHelper;

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
function joinup_rdf_post_update_200003(): string {
  $entity_update_manager = \Drupal::entityDefinitionUpdateManager();
  if (!$entity_update_manager->needsUpdates()) {
    return 'No updates needed.';
  }

  $messages = [];
  $entity_field_manager = \Drupal::getContainer()->get('entity_field.manager');
  foreach ($entity_update_manager->getChangeList() as $entity_type_id => $changes) {
    $entity_type = $entity_update_manager->getEntityType($entity_type_id);
    if (isset($changes['field_storage_definitions'])) {
      $field_storage_definitions = $entity_field_manager->getFieldStorageDefinitions($entity_type_id);
      foreach ($changes['field_storage_definitions'] as $field_name => $value) {
        if ($field_name !== 'og_audience' || $field_name !== 'field_news_referenced_solution') {
          continue;
        }

        /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition */
        $storage_definition = $field_storage_definitions[$field_name];
        $entity_update_manager->updateFieldStorageDefinition($storage_definition);
        $messages[] = "Updated field storage definition for $field_name.";
      }
    }
    $entity_update_manager->updateEntityType($entity_type);
    $messages[] = "Updated entity type $entity_type_id.\n";
  }

  return implode("\n", $messages);
}

/**
 * Restore group reference tables.
 */
function joinup_rdf_deploy_200004(): void {
  $db = \Drupal::database();
  $schema = $db->schema();
  foreach (MigrationHelper::GROUP_REF_TABLES as [$entityTypeId, $fieldName, $hasRevisions]) {
    $tables = ["{$entityTypeId}__$fieldName"];
    if ($hasRevisions) {
      $tables[] = "{$entityTypeId}_revision__$fieldName";
    }
    foreach ($tables as $table) {
      $query = $db->select("b_$table", 'b');
      $query->innerJoin('rdf_sync_uri', 'u', "u.entity_type = '$entityTypeId' AND b.{$fieldName}_target_id = u.uri");
      $query
        ->fields('b', MigrationHelper::DEDICATED_TABLE_COLUMNS)
        ->fields('u', ['entity_id']);
      $db->insert($table)
        ->fields([...MigrationHelper::DEDICATED_TABLE_COLUMNS, "{$fieldName}_target_id"])
        ->from($query)->execute();
      $schema->dropTable("b_$table");
    }
  }
}
