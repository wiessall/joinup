<?php

/**
 * @file
 * Deploy hooks for the joinup_core module.
 */

declare(strict_types=1);

use Drupal\rdf_sync\Model\SyncMethod;
use EasyRdf\Serialiser\Ntriples;

/**
 * Export to Virtuoso the {rdf_sync_uri} entities missing from the endpoint.
 */
function joinup_core_deploy_201400(array &$sandbox): string {
  $batchSize = 100;
  $database = \Drupal::database();
  $connection = \Drupal::service('rdf_sync.connection');
  $synchronizer = \Drupal::service('rdf_sync.synchronizer');
  $entityTypeManager = \Drupal::entityTypeManager();
  $graphUri = \Drupal::config('rdf_sync.settings')->get('graph_uri');

  if (!isset($sandbox['max'])) {
    $sandbox['max'] = (int) $database->select('rdf_sync_uri', 'u')
      ->countQuery()
      ->execute()
      ->fetchField();
    $sandbox['progress'] = 0;
    $sandbox['exported'] = 0;
  }

  // Read the next chunk. {rdf_sync_uri} has no numeric primary key, so paginate
  // on the auto-increment-less table via a stable offset on the progress.
  $rows = $database->select('rdf_sync_uri', 'u')
    ->fields('u', ['entity_type', 'entity_id', 'uri'])
    ->orderBy('u.entity_type')
    ->orderBy('u.entity_id')
    ->range($sandbox['progress'], $batchSize)
    ->execute()
    ->fetchAll();

  if ($rows) {
    // Find which of the chunk URIs already exist as a subject in the graph.
    $serializer = new Ntriples();
    $uris = array_column($rows, 'uri');
    $serialized = array_map(
      static fn(string $uri): string => $serializer->serialiseValue(['type' => 'uri', 'value' => $uri]),
      $uris,
    );
    $inList = implode(', ', $serialized);
    $result = $connection->query("SELECT DISTINCT ?s FROM <$graphUri> WHERE { ?s ?p ?o . FILTER (?s IN ($inList)) }");
    $present = [];
    foreach ($result as $row) {
      $present[(string) $row->s] = TRUE;
    }

    // Group the entities that are missing from the endpoint by entity type.
    $missingByType = [];
    foreach ($rows as $row) {
      if (!isset($present[$row->uri])) {
        $missingByType[$row->entity_type][] = $row->entity_id;
      }
    }

    foreach ($missingByType as $entityTypeId => $entityIds) {
      $storage = $entityTypeManager->getStorage($entityTypeId);
      $entities = $storage->loadMultiple($entityIds);
      if ($entities) {
        $synchronizer->synchronize(SyncMethod::UPDATE, $entities, TRUE);
        $synchronizer->destruct();
        $sandbox['exported'] += count($entities);
      }
      // Avoid memory build-up while iterating over many entities.
      $storage->resetCache($entityIds);
    }

    $sandbox['progress'] += count($rows);
  }

  $sandbox['#finished'] = empty($rows) || $sandbox['progress'] >= $sandbox['max'] ? 1 : ($sandbox['progress'] / $sandbox['max']);
  return "Inspected {$sandbox['progress']} of {$sandbox['max']} {rdf_sync_uri} entries, re-exported {$sandbox['exported']} missing entities so far.";
}
