<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\node\Entity\Node;
use Drupal\pathauto\PathautoState;
use Drupal\search_api\Entity\Index;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Disable RDF Sync for the deployment.
 */
function joinup_rdf_deploy_111000(): void {
  \Drupal::getContainer()->get('rdf_sync.synchronizer')->disableSynchronization();
}

/**
 * Flatten RDF values.
 *
 * @param \stdClass[] $data
 *   The RDF data.
 *
 * @return array
 *   The flattened values.
 */
function _joinup_rdf_deploy_flatten_rdf_values(array $data): array {
  $values = [];
  foreach ($data as $row) {
    $property = $row->property->getUri();
    $value = method_exists($row->value, 'getUri') ? $row->value->getUri() : $row->value->getValue();
    $values[$property][] = $value;
  }
  return $values;
}

/**
 * Migrates releases and their solutions.
 */
function joinup_rdf_deploy_111001(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['releases'])) {
    $query = <<<SPARQL
SELECT DISTINCT ?id
FROM <http://joinup.eu/asset_release/published>
FROM <http://joinup.eu/asset_release/draft>
WHERE {
  ?id a ?type .
}
SPARQL;

    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['releases'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['releases']);
    $sandbox['solution_stubs'] = [];
  }

  $rdf_storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  $uris = array_splice($sandbox['releases'], 0, 50);
  $start = microtime(TRUE);
  foreach ($uris as $uri) {
    $query = <<<SPARQL
SELECT *
FROM <http://joinup.eu/asset_release/published>
FROM <http://joinup.eu/asset_release/draft>
WHERE {
  <$uri> ?property ?value .
}
SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $values = _joinup_rdf_deploy_flatten_rdf_values($results);

    if (empty($values['http://purl.org/dc/terms/isVersionOf'][0]) || !($solution = $rdf_storage->load($values['http://purl.org/dc/terms/isVersionOf'][0]))) {
      throw new Exception('Release ' . $uri . ' has no solution.');
    }

    /** @var \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $solution_stub */
    // Replace the URI with a swapped ID to be able to easily identify the
    // solutions later during the solution migration. Change the `http://` to
    // `swap://` and `https` to `swap-s://` to avoid conflicts with the RDF
    // sync URIs.
    $stub_uri = str_replace(['http://', 'https://'], ['swap://', 'swap-s://'], $solution->id());
    if (!isset($sandbox['solution_stubs'][$stub_uri])) {
      $solution_stub = Node::create([
        'type' => 'solution',
        'title' => strlen($solution->label()) > 255 ? substr($solution->label(), 0, 255) : $solution->label(),
        'uri' => $stub_uri,
      ]);
      // @todo On the UI, we need to handle this when displaying values - link to
      //   the original solution.
      $solution_stub->save();
      $sandbox['solution_stubs'][$stub_uri] = [
        'target_id' => $solution_stub->id(),
        'target_revision_id' => $solution_stub->getRevisionId(),
      ];
    }

    $release_node = Node::create([
      'type' => 'release',
      'title' => strlen($values['http://purl.org/dc/terms/title'][0]) > 255
        ? substr($values['http://purl.org/dc/terms/title'][0], 0, 255)
        : $values['http://purl.org/dc/terms/title'][0],
      'uri' => $uri,
      'is_version_of' => $solution->id(),
      'created' => $values['http://purl.org/dc/terms/issued'][0]->getTimestamp(),
      'changed' => $values['http://purl.org/dc/terms/modified'][0]->getTimestamp(),
      'published_at' => $values['http://purl.org/dc/terms/issued'][0]->getTimestamp(),
      'body' => [
        'value' => $values['http://www.w3.org/ns/adms#versionNotes'][0] ?? '',
        'format' => 'content_editor',
      ],
      'release_number' => $values['http://www.w3.org/2002/07/owl#versionInfo'][0] ?? '',
      'field_state' => $values['http://joinup.eu/isr/workflow_status'][0],
      'field_keywords' => $values['http://www.w3.org/ns/dcat#keyword'] ?? [],
      'access_url' => $values['http://xmlns.com/foaf/0.1/page'][0] ?? NULL,
      'langcode' => $values['http://joinup.eu/language'][0] ?? 'en',
    ]);
    $release_node->set('solution_data', $sandbox['solution_stubs'][$stub_uri]);

    foreach ([
      'http://purl.org/dc/terms/language' => 'language',
      'http://www.w3.org/ns/adms#status' => 'process_status',
      'http://purl.org/dc/terms/spatial' => 'spatial_coverage',
      // @todo Create a follow up to merge the spatial_coverage fields in nodes.
    ] as $predicate => $target_field) {
      $mapped_values = [];
      if (empty($values[$predicate])) {
        continue;
      }

      foreach ($values[$predicate] as $value) {
        if ($term = MappedTerm::loadByUri($value)) {
          $mapped_values[] = [
            'target_id' => $term->id(),
          ];
        }
      }
      $release_node->set($target_field, $mapped_values);
    }

    $release_node->set('uri', $uri);
    $release_node->save();

    $sandbox['progress']++;
  }

  $finish = microtime(TRUE);
  $message = "Progress: {$sandbox['progress']} releases in " . round($finish - $start, 2) . ' s.';
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return $message;
}

/**
 * Clean up the search index.
 */
function joinup_rdf_deploy_111002(): string {
  /** @var \Drupal\search_api\Entity\Index $index */
  $index = Index::load('published');
  $results = $index->query()->addCondition('entity_bundle', 'asset_release')->execute();
  $ids = array_keys($results->getResultItems());
  // ::trackItems{action} re-constructs the IDs so we need to remove the prefix
  // `entity:rdf_entity:` from the IDs.
  $ids = array_map(function (string $id): string {
    return str_replace('entity:rdf_entity/', '', $id);
  }, $ids);
  $index->trackItemsDeleted('entity:rdf_entity', $ids);

  return 'Releases have been removed from the search index.';
}

/**
 * Clean the asset release graph.
 */
function joinup_rdf_deploy_111003(): void {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  $endpoint->query('CLEAR GRAPH <http://joinup.eu/asset_release/published>');
}
