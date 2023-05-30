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
 * Clean up problematic graph in the production database.
 */
function joinup_core_deploy_109300(): string {
  $query = <<<QUERY
DELETE {
  GRAPH ?g {
    ?s ?p ?o .
  }
}
WHERE {
  GRAPH ?g {
    ?s ?p ?o .
  }
  FILTER (STRSTARTS(STR(?g), "no prefix"))
}
QUERY;

  \Drupal::getContainer()->get('sparql.endpoint')->query($query);
  return "Graph has been deleted";
}
