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
 * Change the state name of Releases from 'validated' to 'published'.
 */
function joinup_core_deploy_109800(): string {
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

/**
 * Fix the contact information integer values (UID).
 */
function joinup_core_deploy_109801(): string {
  $database = \Drupal::getContainer()->get('sparql.endpoint');
  $update_query = <<<SPARQL
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
WITH <http://joinup.eu/contact-information/published>
DELETE { ?s <http://joinup.eu/owner/uid> ?o }
INSERT { ?s <http://joinup.eu/owner/uid> ?int }
WHERE {
  ?s <http://joinup.eu/owner/uid> ?o .
  FILTER ( datatype(?o) = <integer> ) .
  BIND (xsd:integer(?o) as ?int) .
}
SPARQL;

  $database->query($update_query);

  return 'Changed the UID of all RDF entities from string to integer.';
}
