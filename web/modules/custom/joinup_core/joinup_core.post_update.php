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

use Drupal\file\Entity\File;
use Drupal\rdf_entity\Entity\Rdf;

/**
 * Revert update of the published state of contact information entities.
 */
function joinup_core_post_update_109800(): string {
  $database = \Drupal::getContainer()->get('sparql.endpoint');
  $update_query = <<<SPARQL
WITH <http://joinup.eu/contact-information/published>
DELETE {
  ?s <http://joinup.eu/contact_information/workflow_status> "published"^^<http://www.w3.org/2001/XMLSchema#string> .
}
INSERT {
  ?s <http://joinup.eu/contact_information/workflow_status> "validated"^^<http://www.w3.org/2001/XMLSchema#string> .
}
WHERE {
  ?s <http://joinup.eu/contact_information/workflow_status> "published"^^<http://www.w3.org/2001/XMLSchema#string> .
}
SPARQL;
  $database->query($update_query);

  return 'Changed the state name of all RDF entities from "published" to "validated".';
}

/**
 * Clean the RDF graph related entities and files.
 */
function joinup_core_post_update_109801(): void {
  $rdf_ids = [
    'http://data.europa.eu/w21/user-graph/e120079b-817b-4c4b-b27b-e97f25cc2c58',
    'http://data.europa.eu/w21/user-graph/ef5c2894-1fa1-43b8-870e-fe55f5e019ae',
  ];
  foreach (Rdf::loadMultiple($rdf_ids) as $rdf) {
    $rdf->delete();
  }
  foreach (File::loadMultiple([525107, 553329]) as $file) {
    $file->delete();
  }
}

/**
 * Clean tallinn data.
 */
function joinup_core_post_update_109802(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $entities = $storage->loadByProperties([
    'type' => 'tallinn_report',
  ]);
  $storage->delete($entities);

  return 'Tallinn data cleaned';
}
