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

use EasyRdf\Graph;

/**
 * Gather information about the current state of solution type terms.
 */
function joinup_core_deploy_200300(): string {
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
function joinup_core_deploy_200301(): void {
  // Import the simplified EIRA 6.0.0.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA_SKOS_v6_0_0.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('solution_type', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();

  // Import EIRA which will clear previous values.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA v6.1.0_SKOS.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('eira', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();

  $graph = new Graph();
  $graph->parseFile('../resources/vocab/eGovERA.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('egovera', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();
}

/**
 * Update the solution type field and revision values.
 */
function joinup_core_deploy_200302(): string {
  $data = \Drupal::state()->get('joinup_core_deploy_200200');
  if (!$data) {
    return 'No data found.';
  }

  $rdf_sync_map = \Drupal::getContainer()->get('rdf_sync.mapper');
  $solution_types = $rdf_sync_map->getUrisByEntityType('taxonomy_term', 'solution_type');

  foreach ($data as $revision_id => $values) {
    $entity_id = $values['entity_id'];
    $values = $values['values'];
    foreach ($values as $original_id => $uri) {
      $altered_uri = str_replace('http://data.europa.eu/dr8/', 'http://data.europa.eu/dr8/6.0.0/', $uri);
      assert(in_array($altered_uri, $solution_types), 'URI not found in the solution type vocabulary.');
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

  return 'Solution type field and revision values have been restored.';
}
