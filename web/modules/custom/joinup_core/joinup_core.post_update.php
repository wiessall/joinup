<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types = 1);

/**
 * Change the state name of all RDF entities from 'validated' to 'published'.
 */
function joinup_core_post_update_109700(): string {
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
      $update_query = <<<SPARQL
WITH <{$graph}>
DELETE {
  ?s <{$predicate}> "validated"^^<http://www.w3.org/2001/XMLSchema#string> .
}
INSERT {
  ?s <{$predicate}> "published"^^<http://www.w3.org/2001/XMLSchema#string> .
}
WHERE {
  ?s <{$predicate}> "validated"^^<http://www.w3.org/2001/XMLSchema#string> .
}
SPARQL;
      $database->query($update_query);
    }
  }

  return 'Changed the state name of all RDF entities from "validated" to "published".';
}

/**
 * Change the state name of all Node entities from 'validated' to 'published'.
 */
function joinup_core_post_update_109701(): string {
  \Drupal::getContainer()->get('database')->update('node__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  // Take care of the revisions.
  \Drupal::getContainer()->get('database')->update('node_revision__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  return 'Changed the state name of all nodes from "validated" to "published".';
}

/**
 * Clean tallinn data.
 */
function joinup_core_post_update_109702(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $entities = $storage->loadByProperties([
    'type' => 'tallinn_report',
  ]);
  $storage->delete($entities);

  return 'Tallinn data cleaned';
}
