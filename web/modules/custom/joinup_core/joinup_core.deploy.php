<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use EasyRdf\Graph;

/**
 * Refresh the spdx_licence vocabulary from resources/vocab/licenses.rdf.
 */
function joinup_core_deploy_201300(): string {
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
    'spdx_licence refreshed: inserted=%d, updated=%d, deleted=%d.',
    $stats['spdx_licence']['inserted'], $stats['spdx_licence']['updated'], $stats['spdx_licence']['deleted'],
  );
}

/**
 * Populates the new published_at field.
 */
function joinup_core_deploy_201301(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('assessment');

  if (!isset($sandbox['total'])) {
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'report')
      ->execute();
    $sandbox['total'] = count($ids);
    $sandbox['ids'] = array_values($ids);
    $sandbox['current'] = 0;
  }

  $items = array_slice($sandbox['ids'], $sandbox['current'], 50);

  foreach ($items as $id) {
    $entity = $storage->load($id);

    if (!$entity) {
      continue;
    }

    $changedTime = $entity->get('changed')->value;
    $entity->set('published_at', $changedTime);
    $entity->save();
    $sandbox['current']++;
  }

  $sandbox['#finished'] = ($sandbox['total'] == 0) ? 1 : ($sandbox['current'] / $sandbox['total']);

  return sprintf('Processed %d of %d assessments.', $sandbox['current'], $sandbox['total']);
}
