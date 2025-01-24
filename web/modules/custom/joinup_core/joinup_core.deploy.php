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
 * Deploy EIRA 6.1.0, simplified solution type (EIRA 6.0.0) and eGovERA.
 */
function joinup_core_deploy_200200(): void {
  // Clean entities of bundle `eira` and `solution_type` from the
  // `rdf_sync_uri` table.
  \Drupal::database()
    ->delete('rdf_sync_uri')
    ->condition('bundle', ['eira', 'solution_type'], 'IN')
    ->execute();

  // Import EIRA first.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA v6.1.0_SKOS.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('eira', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();

  // Import the simplified EIRA 6.0.0.
  $graph = new Graph();
  $graph->parseFile('../resources/vocab/EIRA_SKOS_v6_0_0.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('solution_type', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();

  $graph = new Graph();
  $graph->parseFile('../resources/vocab/eGovERA.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://data.europa.eu/dr8', $graph)
    ->registerVocabulary('egovera', 'http://data.europa.eu/dr8', 'http://data.europa.eu/dr8')
    ->update();
}
