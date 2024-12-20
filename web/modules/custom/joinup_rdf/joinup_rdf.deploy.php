<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

/**
 * Remove http://adms-definition RDF graph.
 */
function joinup_rdf_deploy_200100(): void {
  \Drupal::getContainer()->get('rdf_sync.connection')
    ->update('CLEAR GRAPH <http://adms-definition>;');
}
