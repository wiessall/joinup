<?php

/**
 * @file
 * Deploy hooks for the Joinup licence module.
 */

declare(strict_types=1);

use EasyRdf\Graph;

/**
 * Re-import the spdx_licence vocabulary from resources/vocab/licenses.rdf.
 */
function joinup_licence_deploy_201500(): string {
  $file = DRUPAL_ROOT . '/../resources/vocab/licenses.rdf';
  if (!is_readable($file)) {
    throw new \RuntimeException("SPDX RDF source not readable: $file");
  }

  $graph = new Graph();
  $graph->parseFile($file);

  $stats = \Drupal::service('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://spdx_licence', $graph)
    ->registerVocabulary('spdx_licence', 'http://spdx.org/rdf/terms#License', 'http://spdx_licence')
    ->update();

  return sprintf(
    'spdx_licence re-imported: inserted=%d, updated=%d, deleted=%d.',
    $stats['spdx_licence']['inserted'],
    $stats['spdx_licence']['updated'],
    $stats['spdx_licence']['deleted'],
  );
}
