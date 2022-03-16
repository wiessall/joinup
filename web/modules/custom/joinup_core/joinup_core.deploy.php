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

use Drupal\sparql_entity_storage\Entity\Query\Sparql\SparqlArg;

/**
 * Cleans up terms referenced that are removed.
 */
function joinup_core_deploy_0108000(&$sandbox) {
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $graphs = [
    'http://joinup.eu/solution/published',
    'http://joinup.eu/solution/draft',
    'http://joinup.eu/asset_release/published',
    'http://joinup.eu/asset_release/draft',
  ];

  $terms_to_remove = [
    'http://data.europa.eu/dr8/HostingService',
    'http://data.europa.eu/dr8/PublicServiceCatalogue',
    'http://data.europa.eu/dr8/ServiceRegistrationService',
    'http://data.europa.eu/dr8/HostingAndNetworkingInfrastructure',
    'http://data.europa.eu/dr8/LegalAgreements/InternationalTreaties',
  ];
  $terms_to_remove_string = SparqlArg::serializeUris($terms_to_remove, ' ');

  foreach ($graphs as $graph) {
    $remove_query = <<<QUERY
WITH <{$graph}>
DELETE { ?s <http://purl.org/dc/terms/type> ?solution_type }
WHERE {
  ?s <http://purl.org/dc/terms/type> ?solution_type .
  VALUES ?solution_type { {$terms_to_remove_string} }
}
QUERY;
    $sparql->query($remove_query);

    $replace_query = <<<QUERY
WITH <{$graph}>
DELETE { ?s <http://purl.org/dc/terms/type> <http://data.europa.eu/dr8/PublicService> }
INSERT { ?s <http://purl.org/dc/terms/type> <http://data.europa.eu/dr8/InteroperableDigitalPublicService> }
WHERE { ?s <http://purl.org/dc/terms/type> <http://data.europa.eu/dr8/PublicService> }
QUERY;
    $sparql->query($replace_query);
  }
}
