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

declare(strict_types=1);

/**
 * Cleans up obsolete EIRA terms.
 */
function joinup_core_deploy_110400(): string {
  $count_graph = <<<SPARQL
SELECT ?s ?deletedTermUri
WHERE {
  GRAPH ?g {
    ?s a <http://www.w3.org/ns/dcat#Dataset> .
    ?s <http://purl.org/dc/terms/type> ?deletedTermUri .
    FILTER NOT EXISTS {
      SELECT ?deletedTermUri
      WHERE {
        GRAPH <http://eira_skos> {
          ?deletedTermUri <http://www.w3.org/2004/02/skos/core#topConceptOf> <http://data.europa.eu/dr8> .
        }
      }
    }
  }
}
SPARQL;

  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $count = $sparql->query($count_graph)->numRows();

  $query = <<<SPARQL
DELETE {
  GRAPH ?g {
    ?s <http://purl.org/dc/terms/type> ?deletedTermUri .
  }
}
WHERE {
  GRAPH ?g {
    ?s a <http://www.w3.org/ns/dcat#Dataset> .
    ?s <http://purl.org/dc/terms/type> ?deletedTermUri .
    FILTER NOT EXISTS {
      SELECT ?deletedTermUri
      WHERE {
        GRAPH <http://eira_skos> {
          ?deletedTermUri <http://www.w3.org/2004/02/skos/core#topConceptOf> <http://data.europa.eu/dr8> .
        }
      }
    }
  }
}
SPARQL;

  $result = $sparql->update($query)->current();
  $message = $result->{"callret-0"}->getValue();
  \assert(str_contains($message, (string) $count), 'The number of deleted entries should match the number of entries to clean.');
  \assert($sparql->query($count_graph)->numRows() === 0, 'There should be no more entries to clean.');

  return 'Entries to clean: ' . $count . '. Result: ' . $message . '.';
}
