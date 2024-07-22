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
function joinup_rdf_deploy_111100(): void {
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
 * Migrates published releases and their solutions.
 */
function joinup_rdf_deploy_111101(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['releases'])) {
    $query = <<<SPARQL
SELECT DISTINCT ?id
FROM <http://joinup.eu/asset_release/published>
WHERE {
  ?id a ?type .
}
SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['releases']['published'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    // Also fetch releases from the draft graph, that do not exist in the
    // published graph too - the new releases.
    $query = <<<SPARQL
SELECT DISTINCT ?id
FROM <http://joinup.eu/asset_release/published>
FROM <http://joinup.eu/asset_release/draft>
WHERE {
  GRAPH <http://joinup.eu/asset_release/draft> { ?id a ?type } .
  FILTER NOT EXISTS {
    GRAPH <http://joinup.eu/asset_release/published> {
      ?id a ?type .
    }
  }
}
SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['releases']['draft'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);

    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['releases']['published']) + count($sandbox['releases']['draft']);
    $sandbox['solution_stubs'] = [];
    $sandbox['swapped_aliases'] = 0;
  }
  $alias_repository = \Drupal::getContainer()->get('path_alias.repository');
  $connection = \Drupal::database();
  $rdf_storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  // Use this was to first create the published versions and then the new ones.
  $source = empty($sandbox['releases']['published']) ? 'draft' : 'published';
  $uris = array_splice($sandbox['releases'][$source], 0, 50);
  $start = microtime(TRUE);
  foreach ($uris as $uri) {
    $query = <<<SPARQL
SELECT *
FROM <http://joinup.eu/asset_release/$source>
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
    $stub_uri = str_replace([
      'http://',
      'https://',
    ], [
      'swap://',
      'swap-s://',
    ], $solution->id());

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
      'og_audience' => $solution->id(),
      'created' => $values['http://purl.org/dc/terms/issued'][0]->getTimestamp(),
      'changed' => $values['http://purl.org/dc/terms/modified'][0]->getTimestamp(),
      'published_at' => $values['http://purl.org/dc/terms/issued'][0]->getTimestamp(),
      'description' => [
        'value' => $values['http://www.w3.org/ns/adms#versionNotes'][0] ?? '',
        'format' => 'content_editor',
      ],
      'release_number' => $values['http://www.w3.org/2002/07/owl#versionInfo'][0] ?? '',
      'field_state' => $values['http://joinup.eu/isr/workflow_status'][0],
      'field_keywords' => $values['http://www.w3.org/ns/dcat#keyword'] ?? [],
      'documentation' => $values['http://xmlns.com/foaf/0.1/page'][0] ?? NULL,
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
    $pathauto_old_path = '/rdf_entity/' . UriEncoder::encodeUrl($uri);
    $has_alias = $alias_repository->lookupBySystemPath($pathauto_old_path, 'en');
    if ($has_alias) {
      $release_node->get('path')->pathauto = PathautoState::SKIP;
    }

    $release_node->set('uri', $uri);
    $release_node->setRevisionCreationTime($values['http://purl.org/dc/terms/modified'][0]->getTimestamp());
    $release_node->save();

    _joinup_rdf_deploy_update_field_target_id('node', 'distribution_parent', 'target_id', $uri, $release_node->id());

    if ($has_alias) {
      $internal_url = '/' . $release_node->toUrl()->getInternalPath();
      $connection->update('path_alias')
        ->fields(['path' => $internal_url])
        ->condition('path', $pathauto_old_path)
        ->execute();

      $connection->update('path_alias_revision')
        ->fields(['path' => $internal_url])
        ->condition('path', $pathauto_old_path)
        ->execute();
      $sandbox['swapped_aliases']++;
    }
    $sandbox['progress']++;
  }

  $finish = microtime(TRUE);
  $count = count($uris);
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$count} releases ({$sandbox['progress']} total / {$sandbox['swapped_aliases']} aliases swapped) in " . round($finish - $start, 2) . ' s. Error messages: ' . count($error_messages ?? []);
}

/**
 * Migrates forward release revisions and unpublished ones, and their solutions.
 */
function joinup_rdf_deploy_111102(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  if (empty($sandbox['releases'])) {
    $query = <<<SPARQL
SELECT DISTINCT ?id
FROM <http://joinup.eu/asset_release/draft>
WHERE {
  GRAPH <http://joinup.eu/asset_release/draft> { ?id a ?type } .
  FILTER EXISTS {
    GRAPH <http://joinup.eu/asset_release/published> {
      ?id a ?type .
    }
  }
}
SPARQL;

    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['releases'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['releases']);
    $sandbox['solution_stubs'] = [];
    $sandbox['swapped_aliases'] = 0;
  }

  $rdf_sync = \Drupal::getContainer()->get('rdf_sync.mapper');
  $uris = array_splice($sandbox['releases'], 0, 50);
  $start = microtime(TRUE);
  foreach ($uris as $uri) {
    $query = <<<SPARQL
SELECT *
FROM <http://joinup.eu/asset_release/draft>
WHERE {
  <$uri> ?property ?value .
}
SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $values = _joinup_rdf_deploy_flatten_rdf_values($results);

    if ($release_node = $rdf_sync->getEntityByUri($uri)) {
      // We need to do the same process but create a new revision instead.
      foreach ([
        'http://purl.org/dc/terms/language' => 'language',
        'http://www.w3.org/ns/adms#status' => 'process_status',
        'http://purl.org/dc/terms/spatial' => 'spatial_coverage',
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

      $release_node->set('title', strlen($values['http://purl.org/dc/terms/title'][0]) > 255
        ? substr($values['http://purl.org/dc/terms/title'][0], 0, 255)
        : $values['http://purl.org/dc/terms/title'][0]);
      $release_node->set('created', $values['http://purl.org/dc/terms/issued'][0]->getTimestamp());
      $release_node->set('changed', $values['http://purl.org/dc/terms/modified'][0]->getTimestamp());
      $release_node->set('published_at', $values['http://purl.org/dc/terms/issued'][0]->getTimestamp());
      $release_node->set('description', [
        'value' => $values['http://www.w3.org/ns/adms#versionNotes'][0] ?? '',
        'format' => 'content_editor',
      ]);
      $release_node->set('release_number', $values['http://www.w3.org/2002/07/owl#versionInfo'][0] ?? '');
      $release_node->set('field_state', $values['http://joinup.eu/isr/workflow_status'][0]);
      $release_node->set('field_keywords', $values['http://www.w3.org/ns/dcat#keyword'] ?? []);
      $release_node->set('documentation', $values['http://xmlns.com/foaf/0.1/page'][0] ?? NULL);
      $release_node->set('langcode', $values['http://joinup.eu/language'][0] ?? 'en');

      $release_node->setNewRevision();
      $release_node->setRevisionCreationTime($values['http://purl.org/dc/terms/modified'][0]->getTimestamp());
      $release_node->save();
      _joinup_rdf_deploy_update_field_target_id('node', 'distribution_parent', 'target_id', $uri, $release_node->id());
    }
    $sandbox['progress']++;
  }

  $finish = microtime(TRUE);
  $count = count($uris);
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$count} releases ({$sandbox['progress']} total / {$sandbox['swapped_aliases']} aliases swapped) in " . round($finish - $start, 2) . ' s. Error messages: ' . count($error_messages ?? []);
}

/**
 * Clean up the search index.
 */
function joinup_rdf_deploy_111103(): string {
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
function joinup_rdf_deploy_111104(): void {
  $endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  $endpoint->query('CLEAR GRAPH <http://joinup.eu/asset_release/published>');
  $endpoint->query('CLEAR GRAPH <http://joinup.eu/asset_release/draft>');
}

/**
 * Update the field target ID.
 *
 * @param string $base_table_prefix
 *   The base table prefix without the trailing underscores.
 * @param string $table
 *   The table name.
 * @param string $field
 *   The field name.
 * @param string $old_target_id
 *   The old target ID.
 * @param string $new_target_id
 *   The new target ID.
 * @param bool $include_revisions
 *   Whether to include revisions.
 *
 * @todo Remove this only after the RDF2Node is complete.
 */
function _joinup_rdf_deploy_update_field_target_id(string $base_table_prefix, string $table, string $field, string $old_target_id, string $new_target_id, bool $include_revisions = TRUE): void {
  $connection = \Drupal::database();
  $connection->update($base_table_prefix . '__' . $table)
    ->fields([$table . '_' . $field => $new_target_id])
    ->condition($table . '_' . $field, $old_target_id)
    ->execute();

  if ($include_revisions) {
    $connection->update($base_table_prefix . '_revision__' . $table)
      ->fields([$table . '_' . $field => $new_target_id])
      ->condition($table . '_' . $field, $old_target_id)
      ->execute();
  }
}
