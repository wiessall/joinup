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

declare(strict_types = 1);

use Drupal\Core\File\Exception\FileException;

/**
 * Removal of spam content files.
 */
function joinup_core_deploy_109500(): void {
  /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
  $fileSystem = \Drupal::service('file_system');

  $files = [
    'public://document/2022-12/black-panther-2-wakanda-forever-free-w2tk.pdf',
    'public://document/2022-12/fury-vs-chisora-live-wise.pdf',
    'public://document/2022-12/2021-Crack-Adobe-Illustrator-CC-2018-2521-64Bit-2021-Crack.pdf',
  ];

  foreach ($files as $file) {
    try {
      $fileSystem->delete($file);
    }
    catch (FileException $e) {
    }
  }
}

/**
 * Clean up banner files.
 */
function joinup_core_deploy_109501(array &$sandbox): string {
  if (empty($sandbox['fids'])) {
    $query = <<<SPARQL
select ?o
from <http://joinup.eu/collection/published>
from <http://joinup.eu/collection/draft>
from <http://joinup.eu/solution/published>
from <http://joinup.eu/solution/draft>
from <http://joinup.eu/asset_release/published>
from <http://joinup.eu/asset_release/draft>
where { ?s <http://xmlns.com/foaf/0.1/Image> ?o }
SPARQL;

    $sparql = \Drupal::getContainer()->get('sparql.endpoint');
    $results = $sparql->query($query);
    foreach ($results as $result) {
      $sandbox['fids'][] = $result->o->getValue();
    }
    $sandbox['total'] = count($sandbox['fids']);
    $sandbox['current'] = 0;
  }

  $file_system = \Drupal::getContainer()->get('file_system');
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');

  $fids = array_splice($sandbox['fids'], $sandbox['current'], 100);
  foreach ($file_storage->loadMultiple($fids) as $file) {
    $uri = $file->getFileUri();
    if ($file_path = $file_system->realpath($uri)) {
      $file_system->delete($file_path);
    }
    $file->delete();
  }

  $sandbox['current'] += count($fids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  return 'Processed ' . $sandbox['current'] . ' of ' . $sandbox['total'] . ' files.';
}

/**
 * Clean up banner database entries.
 */
function joinup_core_deploy_109502(): void {
  $sparql_endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  $query = <<<SPARQL
DELETE { GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } }
WHERE {
  GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } .
  VALUES ?g { <http://joinup.eu/collection/published>  <http://joinup.eu/collection/draft> <http://joinup.eu/solution/published> <http://joinup.eu/solution/draft> <http://joinup.eu/asset_release/published> <http://joinup.eu/asset_release/draft> }
}
SPARQL;

  $sparql_endpoint->update($query);
}

