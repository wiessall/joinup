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
 * Change the state name of all RDF entities from 'validated' to 'published'.
 */
function joinup_core_deploy_109700(): string {
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
function joinup_core_deploy_109701(): string {
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
 * Dismantle EIF Recommendation view.
 */
function joinup_core_deploy_109702(): void {
  \Drupal::entityTypeManager()->getStorage('redirect')->load(125784)->delete();
}

/**
 * Change the state name of all RDF entities from 'validated' to 'published'.
 */
function joinup_core_deploy_109703(): string {
  $database = \Drupal::getContainer()->get('sparql.endpoint');
  $workflow_urls = [
    'http://joinup.eu/isr/workflow_status' => [
      'http://joinup.eu/asset_release/published',
      'http://joinup.eu/asset_release/draft',
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
