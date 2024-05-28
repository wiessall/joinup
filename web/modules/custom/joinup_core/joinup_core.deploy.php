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

use Drupal\Core\Utility\UpdateException;
use Drupal\joinup_licence\Entity\LicenceInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\sparql_entity_storage\Driver\Database\sparql\Connection;

/**
 * Assign "Other" licence to asset distributions referring missing licences.
 */
function joinup_core_deploy_110800(): void {
  $missingIds = [
    'http://data.europa.eu/w21/dbd78bd7-c584-48c0-8c4d-be72cfe9e259',
    'http://data.europa.eu/w21/db09e2bf-a3bf-458f-bd4c-3460646dd44b',
  ];
  $sparqlConnection = \Drupal::service('sparql.endpoint');
  assert($sparqlConnection instanceof Connection);
  $syncMapper = \Drupal::service('rdf_sync.mapper');
  assert($syncMapper instanceof RdfSyncMapper);
  $newId = 'http://data.europa.eu/w21/c222b552-bce2-415d-956c-d731c0f97615';
  if (!$syncMapper->getEntityByUri($newId) instanceof LicenceInterface) {
    throw new UpdateException('Replacement term is missing.');
  }

  foreach ($missingIds as $oldId) {
    // Asset distros only have one graph.
    $query = <<<SPARQL
WITH <http://joinup.eu/asset_distribution/published>
DELETE { ?id <http://purl.org/dc/terms/license> <$oldId> }
INSERT { ?id <http://purl.org/dc/terms/license> <$newId> }
WHERE { ?id <http://purl.org/dc/terms/license> <$oldId> }
SPARQL;
    $sparqlConnection->query($query);
  }

}
