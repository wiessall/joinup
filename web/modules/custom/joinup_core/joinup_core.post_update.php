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
