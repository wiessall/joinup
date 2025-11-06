<?php

/**
 * @file
 * Install, update, uninstall hook implementations for Joinup Taxonomy module.
 */

declare(strict_types=1);

use EasyRdf\Graph;

/**
 * Update 'spatial' vocabulary.
 */
function joinup_taxonomy_deploy_200700(): void {
  // Save the new countries-skos.rdf file locally.
  $response = \Drupal::httpClient()->get('http://publications.europa.eu/resource/distribution/country/20241211-0/rdf/skos_core/countries-skos.rdf');
  file_put_contents('temporary://countries-skos.rdf', $response->getBody()->getContents());

  // Update the vocabulary.
  $graph = new Graph();
  $graph->parseFile('temporary://countries-skos.rdf');
  \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://spatial', $graph)
    ->registerVocabulary('spatial', 'http://publications.europa.eu/resource/authority/country', 'http://spatial')
    ->update();

  unlink('temporary://countries-skos.rdf');
}
