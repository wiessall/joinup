<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\rdf_sync\Model\SyncMethod;
use EasyRdf\Serialiser\Ntriples;

/**
 * Resets, re-aliases and re-queues the curated homepage topic translations.
 */
function joinup_core_deploy_201500(array &$sandbox): string {
  $queue = \Drupal::entityTypeManager()->getStorage('entity_subqueue')->load('homepage_topics');
  $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $pathauto_generator = \Drupal::hasService('pathauto.generator') ? \Drupal::service('pathauto.generator') : NULL;

  $removed = 0;
  $requeued = 0;
  $realiased = 0;
  foreach ($queue->get('items')->referencedEntities() as $entity) {
    if (!$entity instanceof MetaEntityInterface) {
      continue;
    }
    $meta_entity = $entity->getUntranslated();

    // Drop every non-English translation.
    $non_english = array_diff(array_keys($meta_entity->getTranslationLanguages()), ['en']);
    if ($non_english) {
      foreach ($non_english as $langcode) {
        $meta_entity->removeTranslation($langcode);
        $removed++;
      }
      $meta_entity->save();
    }

    // Replace the shared language-neutral alias with a per-language one.
    if ($pathauto_generator) {
      $aliases = $alias_storage->loadByProperties([
        'path' => '/' . $meta_entity->toUrl('canonical')->getInternalPath(),
      ]);
      if ($aliases) {
        $alias_storage->delete($aliases);
      }
      foreach (array_keys($meta_entity->getTranslationLanguages()) as $langcode) {
        $pathauto_generator->updateEntityAlias($meta_entity->getTranslation($langcode), 'update');
      }
      $realiased++;
    }

    // Re-queue the English source for (re-)translation.
    tmgmt_content_create_continuous_job_items($meta_entity);
    $requeued++;
  }

  return sprintf('Removed %d non-English translation(s), regenerated aliases for %d homepage topic(s) and re-queued %d for translation.', $removed, $realiased, $requeued);
}

/**
 * Export to Virtuoso the {rdf_sync_uri} entities missing from the endpoint.
 */
function joinup_core_deploy_201501(array &$sandbox): string {
  $start = microtime(TRUE);
  $batchSize = 100;
  // Number of entities to export per SPARQL INSERT. Each statement compiles to
  // Virtuoso SQL and must stay under its SP031 limit ("the length of generated
  // SQL text has exceeded 10000 lines of code"). Entities with many triples
  // (datasets/solutions with large descriptions, many distributions/versions)
  // overflow a 100-entity INSERT, so the inserts are sent in small chunks.
  $insertChunkSize = 10;
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
    // Use a VALUES clause rather than FILTER (?s IN (...)): Virtuoso resolves
    // VALUES through the subject index, while FILTER IN forces a full scan of
    // the graph on every batch, which degrades as the graph grows.
    $values = implode(' ', $serialized);
    $result = $connection->query("SELECT DISTINCT ?s FROM <$graphUri> WHERE { VALUES ?s { $values } ?s ?p ?o }");
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
      // Export in small chunks so each SPARQL INSERT stays under Virtuoso's
      // SP031 generated-SQL limit.
      foreach (array_chunk($entityIds, $insertChunkSize) as $chunk) {
        $entities = $storage->loadMultiple($chunk);
        if ($entities) {
          // These entities are confirmed missing from the graph, so INSERT is
          // enough. UPDATE would additionally run a full-graph delete scan for
          // triples that don't exist.
          $synchronizer->synchronize(SyncMethod::INSERT, $entities, TRUE);
          $synchronizer->destruct();
          $sandbox['exported'] += count($entities);
        }
        // Avoid memory build-up while iterating over many entities.
        $storage->resetCache($chunk);
      }
    }

    $sandbox['progress'] += count($rows);
  }

  $sandbox['#finished'] = empty($rows) || $sandbox['progress'] >= $sandbox['max'] ? 1 : ($sandbox['progress'] / $sandbox['max']);
  $elapsed = microtime(TRUE) - $start;
  $sandbox['elapsed'] = ($sandbox['elapsed'] ?? 0) + $elapsed;
  return sprintf(
    'Inspected %d of %d {rdf_sync_uri} entries, re-exported %d missing entities so far. Iteration took %.2fs (total %.2fs).',
    $sandbox['progress'],
    $sandbox['max'],
    $sandbox['exported'],
    $elapsed,
    $sandbox['elapsed'],
  );
}
