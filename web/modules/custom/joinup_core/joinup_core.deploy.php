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
 * Clears TRR fields for potential cases.
 */
function joinup_core_deploy_0108700(array &$sandbox): void {
  $field_predicates = [
    'http://joinup.ec.europa.eu/trr#actor',
    'http://joinup.ec.europa.eu/trr#businessProcess',
    'http://joinup.ec.europa.eu/trr#productType',
    'http://joinup.ec.europa.eu/trr#StandardizationLevel',
    'http://joinup.eu/test_resource_type',
  ];
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $graphs = [
    '<http://joinup.eu/solution/published>',
    '<http://joinup.eu/solution/draft>',
  ];
  foreach ($graphs as $graph) {
    foreach ($field_predicates as $field_predicate) {
      $query = <<<QUERY
WITH {$graph}
DELETE { ?entity_id <{$field_predicate}> ?value }
WHERE {
    ?entity_id a ?type .
    FILTER NOT EXISTS {
      ?entity_id <http://purl.org/dc/terms/type> ?trr_value .
      VALUES ?trr_value { <http://data.europa.eu/dr8/ConformanceTestingComponent> <http://data.europa.eu/dr8/ConformanceTestingService> <http://data.europa.eu/dr8/ConformanceTestScenario> }
    }
    ?entity_id <{$field_predicate}> ?value
}
QUERY;
      $sparql->query($query);
    }
  }
}

/**
 * Queue contact information emails for validation.
 */
function joinup_core_deploy_108701(array &$sandbox): void {
  $queue = \Drupal::queue('joinup_email_confirmer');
  $queue->createQueue();

  /** @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql */
  $sparql = \Drupal::service('sparql.endpoint');
  $rows = $sparql->query('SELECT DISTINCT(?email)
    WHERE {
      GRAPH <http://joinup.eu/contact-information/published> {
        ?id <http://www.w3.org/2006/vcard/ns#hasEmail> ?email .
        ?id a <http://www.w3.org/2006/vcard/ns#Kind> .
      }
    }
    ORDER BY (?email)')->getArrayCopy();
  array_walk($rows, function (\stdClass $row) use ($queue): void {
    $queue->createItem(['email' => $row->email->getValue()]);
  });
}
