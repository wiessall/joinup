<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Remove site_verify leftovers.
 */
function joinup_core_deploy_108200(): void {
  \Drupal::database()->schema()->dropTable('site_verify');
}

/**
 * Update the predicates for field 'test_resource_type'.
 */
function joinup_core_deploy_108201() {
  /** @var \Drupal\Core\Database\Connection $query */
  $query = \Drupal::service('sparql.endpoint');
  $test_resource_type_query = <<<QUERY
DELETE { GRAPH ?g { ?subject <http://purl.org/dc/terms/DCMIType> ?object } }
INSERT { GRAPH ?g { ?subject <http://joinup.eu/test_resource_type> ?object } }
USING <http://joinup.eu/asset_release/draft>
USING <http://joinup.eu/asset_release/published>
WHERE { GRAPH ?g { ?subject <http://purl.org/dc/terms/DCMIType> ?object } }
QUERY;
  $query->query($test_resource_type_query);

  $orphaned_triples_query = <<<QUERY
DELETE { GRAPH ?g { ?s <http://joinup.eu/test_resource_type> ?o } }
WHERE { ?s <http://joinup.eu/test_resource_type> ?o
  . VALUES ?g {<http://joinup.eu/asset_release/published> <http://joinup.eu/asset_release/draft> }
  . FILTER NOT EXISTS { ?s a ?type }
}
QUERY;
  $query->query($orphaned_triples_query);
}

/**
 * Set announcements_enabled enable in collections/solutions.
 */
function joinup_core_deploy_108202(): string {
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = \Drupal::entityQuery('rdf_entity')->condition('rid', [
      'collection',
      'solution',
    ], 'IN')->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $entity_storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  $nids = array_splice($sandbox['ids'], 0, 50);
  foreach ($entity_storage->loadMultiple($nids) as $entity) {
    $meta_entity_name = $entity->bundle() === 'collection' ? 'collection_settings' : 'solution_settings';
    $meta_entity = MetaEntity::loadOrCreate($meta_entity_name, $entity);
    $meta_entity->set('announcements_enabled', TRUE);
    $meta_entity->save();
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Updated {$sandbox['count']} out of {$sandbox['max']} meta-entity settings collection/solution.";
}
