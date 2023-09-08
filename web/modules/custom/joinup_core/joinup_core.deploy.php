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
use Drupal\search_api\Entity\Index;

/**
 * Change the state name of all RDF entities from 'validated' to 'published'.
 */
function joinup_core_deploy_109700(): string {
  $entity_ids = [];
  $database = \Drupal::getContainer()->get('sparql.endpoint');

  $workflow_urls = [
    'http://joinup.eu/ar/workflow_status' => [
      'http://joinup.eu/collection/published',
      'http://joinup.eu/collection/draft',
    ],
    'http://joinup.eu/contact_information/workflow_status' => [
      'http://joinup.eu/contact-information/published',
    ],
    'http://joinup.eu/is/workflow_status' => [
      'http://joinup.eu/solution/published',
      'http://joinup.eu/solution/draft',
    ],
    'http://joinup.eu/isr/workflow_status' => [
      'http://joinup.eu/asset_distribution/published',
      'http://joinup.eu/asset_distribution/draft',
    ],
    'http://joinup.eu/owner/workflow_status' => [
      'http://joinup.eu/owner/published',
    ],
  ];

  foreach ($workflow_urls as $predicate => $graphs) {
    foreach ($graphs as $graph) {
      $select_query = <<<SPARQL
SELECT ?s
WHERE {
  GRAPH <{$graph}> {
    ?s <{$predicate}> "validated" .
  }
}
SPARQL;

      $results = array_map(function(\stdClass $result): string {
        return $result->s->getUri();
      }, $database->query($select_query)->getArrayCopy());
      $entity_ids = array_merge($entity_ids, $results);

      // @todo check if the state is a plain string.
      $query = <<<SPARQL
WITH <{$graph}>
DELETE { ?s <{$predicate}> "validated" }
INSERT { ?s <{$predicate}> "published" }
WHERE { ?s <{$predicate}> "validated" }
SPARQL;
    }
  }

  \Drupal::getContainer()->get('joinup_search.index_helper')->reIndexItems('rdf_entity', $entity_ids);
  return 'Changed the state name of all RDF entities from "validated" to "published".';
}

/**
 * Update the node's field_state value from 'validated' to 'published'.
 */
function joinup_core_deploy_109701(): string {
  // Get the IDs of nodes that have the field_state set to 'validated'.
  $query = \Drupal::getContainer()->get('database')->select('node__field_state', 'nfs');
  $query->addField('nfs', 'entity_id');
  $query->condition('nfs.field_state_value', 'validated');
  $nids = $query->execute()->fetchCol();

  \Drupal::getContainer()->get('database')->update('node__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  // Take care of the revisions.
  \Drupal::getContainer()->get('database')->update('node_revision__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  \Drupal::getContainer()->get('joinup_search.index_helper')->reIndexItems('node', $nids);
  return 'Changed the state name of all nodes from "validated" to "published".';
}
