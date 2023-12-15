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
 * Alter abstracts of collections.
 */
function joinup_core_deploy_110100(array &$sandbox): string {
  $sparql_connection = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['data'])) {
    $query = <<<SPARQL
SELECT ?graph ?collection ?abstract
WHERE {
    GRAPH ?graph {
        ?collection <http://purl.org/dc/terms/abstract> ?abstract .
    }
    VALUES ?graph {
        <http://joinup.eu/collection/published>
        <http://joinup.eu/collection/draft>
    }
}
SPARQL;

    $results = $sparql_connection->query($query);
    foreach ($results as $result) {
      $sandbox['data'][] = [
        'graph' => $result->graph->getUri(),
        'collection' => $result->collection->getUri(),
        'abstract' => $result->abstract->getValue(),
      ];
    }
    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['data']);
  }

  $spliced = array_splice($sandbox['data'], 0, 300);
  foreach ($spliced as $item) {
    $item['abstract'] = trim(strip_tags($item['abstract']));
    $characters_to_replace = [
      '"' => '\\"',
      "'" => "\\'",
      "\xc2\xa0" => ' ',
      // The non-breaking space is stored as plain text after the tag stripping.
      "&nbsp;" => ' ',
    ];
    $item['abstract'] = strtr($item['abstract'], $characters_to_replace);
    // Replace multiple spaces with a single space.
    $item['abstract'] = preg_replace('/\s+/', ' ', $item['abstract']);

    $query = <<<SPARQL
WITH <{$item['graph']}>
DELETE {
    <{$item['collection']}> <http://joinup.eu/text_format> ?format .
    <{$item['collection']}> <http://purl.org/dc/terms/abstract> ?abstract .
}
INSERT {
    <{$item['collection']}> <http://purl.org/dc/terms/abstract> "{$item['abstract']}" .
}
WHERE {
    <{$item['collection']}> <http://purl.org/dc/terms/abstract> ?abstract .
}
SPARQL;
    $sparql_connection->update($query);
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']}.";
}
