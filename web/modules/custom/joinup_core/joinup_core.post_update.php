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

declare(strict_types=1);

use Drupal\sparql_entity_storage\Entity\Query\Sparql\SparqlArg;

/**
 * Remove TRR content.
 */
function joinup_core_post_update_110300(array &$sandbox): void {
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $graphs = [
    'http://joinup.eu/solution/published',
    'http://joinup.eu/solution/draft',
    'http://joinup.eu/asset_distribution/published',
  ];
  $predicates = SparqlArg::serializeUris([
    'http://joinup.ec.europa.eu/trr#actor',
    'http://joinup.ec.europa.eu/trr#businessProcess',
    'http://joinup.ec.europa.eu/trr#productType',
    'http://joinup.ec.europa.eu/trr#StandardizationLevel',
    'http://joinup.eu/test_resource_type',
    'http://joinup.eu/asset_distribution/gitb_compliant',
  ]);
  $vocabs = [
    'http://actor',
    'http://business-process',
    'http://product_type',
    'http://standardization_level',
    'http://test_resource_type',
  ];

  $query = <<<QUERY
    WITH <%s>
    DELETE { ?s ?p ?o }
    WHERE {
      ?s ?p ?o .
      FILTER( ?p IN (
        $predicates
      )) .
    }
    QUERY;

  // Remove field references.
  foreach ($graphs as $graph) {
    $sparql->update(sprintf($query, $graph));
  }

  // Remove terms.
  foreach ($vocabs as $vocab) {
    $sparql->query("CLEAR GRAPH <$vocab>");
  }
}
