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
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\joinup_assessment\AssessmentInterface;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;
use EasyRdf\Graph;

/**
 * Add an /events redirect.
 */
function joinup_core_deploy_200300(): void {
  \Drupal::entityTypeManager()->getStorage('redirect')->create([
    'redirect_source' => ['path' => 'events'],
    'redirect_redirect' => ['uri' => 'internal:/node/726609'],
    'language' => 'und',
    'status_code' => 301,
  ])->save();
}

/**
 * Update pathauto state for "RDF" nodes with aliases.
 */
function joinup_core_deploy_200301(array &$sandbox): string {
  $database = Database::getConnection();

  if (!isset($sandbox['ids'])) {
    $query = $database->select('node_field_data', 'nd');
    $query->fields('nd', ['nid'])
      ->condition('nd.type', [
        'collection',
        'solution',
        'distribution',
        'release',
        'contact_information',
        'owner',
      ], 'IN');
    $query->join('node', 'n', 'n.nid = nd.nid');
    $query->fields('n', ['uuid'])
      ->condition('n.uuid', [
        InteroperableEuropeCollectionInterface::COLLECTION_UUID,
        OssCatalogueCollectionInterface::COLLECTION_UUID,
        AssessmentInterface::GROUP_UUID,
      ], 'NOT IN');
    $query->join('key_value', 'k', 'nd.nid = k.name AND k.collection = :collection', [':collection' => 'pathauto_state.node']);
    $query->condition('k.value', 'i:0;');

    $sandbox['ids'] = $query->execute()->fetchCol();
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;
  }

  $ids = array_splice($sandbox['ids'], 0, 200);
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

/**
 * Gather information about the current state of solution type terms.
 */
function joinup_core_deploy_200302(): string {
  // We only need to check the revision table as all data are there including
  // the data from the data table.
  $solution_type_values = \Drupal::database()
    ->select('node_revision__solution_type', 'nrst')
    ->fields('nrst', ['entity_id', 'revision_id', 'solution_type_target_id'])
    ->execute()
    ->fetchAll();

  $target_ids = array_column($solution_type_values, 'solution_type_target_id');
  $eira_terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadMultiple($target_ids);

  $target_ids_to_uri = [];
  foreach ($eira_terms as $eira_term) {
    $target_ids_to_uri[$eira_term->id()] = $eira_term->getUri();
  }

  $data = [];
  foreach ($solution_type_values as $solution_type_value) {
    if (!isset($data[$solution_type_value->revision_id])) {
      $data[$solution_type_value->revision_id] = [
        'entity_id' => $solution_type_value->entity_id,
        'revision_id' => $solution_type_value->revision_id,
      ];
    }

    $data[$solution_type_value->revision_id]['values'][$solution_type_value->solution_type_target_id] = $target_ids_to_uri[$solution_type_value->solution_type_target_id];
  }

  \Drupal::state()->set('joinup_core_deploy_200200', $data);
  return 'Data has been gathered.';
}

/**
 * Deploy EIRA 6.1.0, simplified solution type (EIRA 6.0.0) and eGovERA.
 */
function joinup_core_deploy_200303(): void {
  // Import the simplified EIRA 6.0.0.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA_SKOS_v6_0_0.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8/6.0.0', $graph)
    ->registerVocabulary('solution_type', 'http://data.europa.eu/dr8/6.0.0', 'http://data.europa.eu/dr8/6.0.0')
    ->update();

  // Re import eGovERA in order to clean the rest of the terms.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/eGovERA.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8/egovera', $graph)
    ->registerVocabulary('egovera', 'http://data.europa.eu/dr8/egovera', 'http://data.europa.eu/dr8/egovera')
    ->update();

  // Import EIRA which will clear previous values.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA v6.1.0_SKOS.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('eira', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();
}

/**
 * Update the solution type field and revision values.
 */
function joinup_core_deploy_200304(): string {
  $data = \Drupal::state()->get('joinup_core_deploy_200200');
  if (!$data) {
    throw new Exception('No relevant data found about solution type field.');
  }

  $rdf_sync_map = \Drupal::getContainer()->get('rdf_sync.mapper');
  $solution_types = $rdf_sync_map->getUrisByEntityType('taxonomy_term', 'solution_type');

  foreach ($data as $revision_id => $values) {
    $entity_id = $values['entity_id'];
    $values = $values['values'];
    foreach ($values as $original_id => $uri) {
      $altered_uri = str_replace('http://data.europa.eu/dr8/', 'http://data.europa.eu/dr8/6.0.0/', $uri);
      // The following line also ensures that every single entry will be
      // replaced with an existing solution type term of the same URI.
      assert(in_array($altered_uri, $solution_types), 'URI ' . $altered_uri . ' not found in the solution type vocabulary.');
      $term = $rdf_sync_map->getEntityByUri($altered_uri);

      foreach (['node__solution_type', 'node_revision__solution_type'] as $table) {
        \Drupal::database()
          ->update($table)
          ->condition('entity_id', $entity_id)
          ->condition('revision_id', $revision_id)
          ->condition('solution_type_target_id', $original_id)
          ->fields(['solution_type_target_id' => $term->id()])
          ->execute();
      }
    }
  }

  \Drupal::state()->delete('joinup_core_deploy_200200');
  return 'Solution type field and revision values have been restored.';
}

/**
 * Delete content of an orphaned group.
 */
function joinup_core_deploy_200305(array &$sandbox): string {
  if (empty($sandbox['nids'])) {
    $query = <<<SQL
      SELECT n1.nid, n1.type, n1.title, n2.type, n2.title
      FROM node_field_data n1
        LEFT JOIN node__og_audience oa ON n1.nid = oa.entity_id
        LEFT JOIN node_field_data n2 ON oa.og_audience_target_id = n2.nid
      WHERE n1.type IN('news', 'discussion', 'document', 'event', 'solution', 'distribution', 'release')
        AND n2.nid IS NULL
      SQL;

    $sandbox['nids'] = \Drupal::database()->query($query)->fetchCol();

    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['progress'] = 0;
  }

  $nids = array_splice($sandbox['nids'], 0, 50);
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $storage->delete($storage->loadMultiple($nids));

  $sandbox['progress'] += count($nids);
  $sandbox['#finished'] = (int) empty($sandbox['nids']);

  return "Deleted {$sandbox['progress']} out of {$sandbox['total']} orphaned content.";
}

/**
 * Mark all releases as needing reindexing.
 */
function joinup_core_deploy_200306(array &$sandbox): string {
  if (empty($sandbox['current'])) {
    $sandbox['current'] = 0;
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'release')
      ->condition('status', 1);
    $sandbox['nids'] = $query->execute();
    if (empty($sandbox['nids'])) {
      return 'No releases found.';
    }
    $sandbox['total'] = count($sandbox['nids']);
  }
  $index_id = 'published';
  $datasource_id = 'entity:node';
  $index = Index::load($index_id);

  $nids = array_splice($sandbox['nids'], 0, 50);
  $document_ids = [];
  foreach ($nids as $nid) {
    $document_ids[] = Utility::createCombinedId($datasource_id, $nid . ':' . \Drupal::languageManager()->getCurrentLanguage()->getId());
  }

  $documents = $index->loadItemsMultiple($document_ids);
  $index->indexSpecificItems($documents);

  $sandbox['current'] += count($nids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  return sprintf('Reindexed %d of %d releases.', $sandbox['current'], $sandbox['total']);
}

/**
 * Remove users inactive for 3 years.
 */
function joinup_core_deploy_200307(array &$sandbox): string {
  $queue = \Drupal::queue('joinup_removed_users');

  if (empty($sandbox['usernames'])) {
    $sandbox['usernames'] = str_getcsv(file_get_contents(__DIR__ . "/fixtures/inactive_users.csv"));
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['usernames']);
  }

  $usernames = array_splice($sandbox['usernames'], 0, 500);

  $queue->createQueue();
  foreach ($usernames as $username) {
    $queue->createItem(['name' => $username]);
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} of users.";
}

/**
 * Delete rdf sync for topic vocabulary.
 */
function joinup_core_deploy_200308(array &$sandbox): string {
  if (!isset($sandbox['total'])) {
    $sandbox['total'] = \Drupal::database()->query("SELECT COUNT(*) FROM {rdf_sync_uri} WHERE bundle = :bundle", [
      ':bundle' => 'topic',
    ])->fetchField();
    $sandbox['current'] = 0;
  }
  $query = \Drupal::database()->delete('rdf_sync_uri')
    ->condition('bundle', 'topic');
  $deleted = $query->execute();
  $sandbox['current'] += $deleted;
  if ($sandbox['current'] >= $sandbox['total']) {
    return sprintf('Deletion of RDF storage for "topic" vocabulary completed. %d records removed.', $sandbox['total']);
  }
}
