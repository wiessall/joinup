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

/**
 * Clean up banner files.
 */
function joinup_core_deploy_109600(array &$sandbox): string {
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

  $fids = array_splice($sandbox['fids'], 0, 100);
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $file_storage->delete($file_storage->loadMultiple($fids));

  $sandbox['current'] += count($fids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  return 'Processed ' . $sandbox['current'] . ' of ' . $sandbox['total'] . ' files.';
}

/**
 * Clean up banner database entries.
 */
function joinup_core_deploy_109601(): void {
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
