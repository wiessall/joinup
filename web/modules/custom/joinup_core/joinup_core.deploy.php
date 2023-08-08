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

use Drupal\joinup_media\Plugin\QueueWorker\MigrateMedia;

/**
 * Clean up banner files.
 */
function joinup_core_deploy_109600(array &$sandbox): string {
  if (empty($sandbox['fids'])) {
    $query = <<<SPARQL
select ?o
from <http://joinup.eu/collection/published>
from <http://joinup.eu/collection/draft>
from <http://joinup.eu/solution/published>
from <http://joinup.eu/solution/draft>
from <http://joinup.eu/asset_release/published>
from <http://joinup.eu/asset_release/draft>
where { ?s <http://xmlns.com/foaf/0.1/Image> ?o }
SPARQL;

    $sparql = \Drupal::getContainer()->get('sparql.endpoint');
    $results = $sparql->query($query);
    foreach ($results as $result) {
      $sandbox['fids'][] = $result->o->getValue();
    }
    $sandbox['total'] = count($sandbox['fids']);
    $sandbox['current'] = 0;
  }

  $fids = array_splice($sandbox['fids'], 0, 100);
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $file_storage->delete($file_storage->loadMultiple($fids));

  $sandbox['current'] += count($fids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  return 'Processed ' . $sandbox['current'] . ' of ' . $sandbox['total'] . ' files.';
}

/**
 * Clean up banner database entries.
 */
function joinup_core_deploy_109601(): void {
  $sparql_endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  $query = <<<SPARQL
DELETE { GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } }
WHERE {
  GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } .
  VALUES ?g { <http://joinup.eu/collection/published>  <http://joinup.eu/collection/draft> <http://joinup.eu/solution/published> <http://joinup.eu/solution/draft> <http://joinup.eu/asset_release/published> <http://joinup.eu/asset_release/draft> }
}
SPARQL;

  $sparql_endpoint->update($query);
}

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_109602(array &$sandbox): string {
  $entity_type_manager = \Drupal::entityTypeManager();
  $node_storage = $entity_type_manager->getStorage('node');
  $db = \Drupal::database();

  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'video')
      ->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }

  $nids = array_splice($sandbox['ids'], 0, 50);
  /** @var \Drupal\joinup_video\Entity\VideoInterface $video */
  foreach ($node_storage->loadMultiple($nids) as $video) {
    if ($url = $video->get('field_video')->value) {
      $media = MigrateMedia::getMedia('video', $url, (int) $video->getOwnerId(), $video->get('og_audience')->target_id);
      $options = [
        'bundle' => 'video',
        'entity_id' => $video->id(),
        'revision_id' => $video->getRevisionId(),
        'langcode' => 'en',
        'delta' => 0,
        'video_target_id' => $media->id(),
      ];
      $db->insert('node_revision__video')->fields($options)->execute();
      $db->insert('node__video')->fields($options)->execute();
    }
    $sandbox['count']++;
  }
  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];

  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed fields.";
}

/**
 * Migrate videos and images embed markup into media.
 */
function joinup_core_deploy_109603(array &$sandbox): string {
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $db = \Drupal::database();
  $queue = \Drupal::queue('joinup_media_migrate');

  if (empty($sandbox['tasks'])) {
    $sql = <<<QUERY
      SELECT b.revision_id, g.og_audience_target_id, IF(b.revision_id = n.vid, 1, 0) AS current_revision, n.uid
      FROM {node_revision__body} b
      INNER JOIN {node__og_audience} g ON b.entity_id = g.entity_id
      INNER JOIN {node_field_data} n ON b.entity_id = n.nid
      WHERE b.deleted = 0
      AND (b.body_value LIKE '%preview_thumbnail%' OR b.body_value LIKE '%<img%data-entity-type="file"%')
      QUERY;
    $sandbox['tasks']['node'] = array_map(function (array $item): array {
      return [(int) $item[0], $item[1], (bool) $item[2], (int) $item[3]];
    }, $db->query($sql)->fetchAll(\PDO::FETCH_NUM));

    // The most relevant part in this query is getting the topmost node ID as we
    // need the node's group ID and owner but the paragraphs are nested.
    // @see https://dba.stackexchange.com/questions/291321/get-the-highest-parent-id-recursively-in-mysql
    $sql = <<<QUERY
      WITH RECURSIVE cte AS (
        SELECT id, type, parent_id, parent_id AS topmost_nid, revision_id
          FROM {paragraphs_item_field_data}
          WHERE parent_type = 'node'
        UNION ALL
        SELECT t.id, t.type, t.parent_id, cte.topmost_nid, t.revision_id
          FROM {paragraphs_item_field_data} t
          INNER JOIN cte ON t.parent_id = cte.id
      )
      SELECT r.revision_id, o.og_audience_target_id, IF(cte.revision_id = r.revision_id, 1, 0) AS current_revision, n.uid
        FROM cte
        INNER JOIN {node__og_audience} o ON cte.topmost_nid = o.entity_id
        INNER JOIN {node_field_data} n ON cte.topmost_nid = n.nid
        LEFT JOIN {paragraphs_item_revision_field_data} r ON cte.id = r.id
        LEFT JOIN {paragraph_revision__field_body} b ON r.revision_id = b.revision_id
        WHERE cte.type = 'simple_paragraph'
        AND (b.field_body_value LIKE '%preview_thumbnail%' OR b.field_body_value LIKE '%<img%data-entity-type="file"%')
      QUERY;
    $sandbox['tasks']['paragraph'] = array_map(function (array $item): array {
      return [(int) $item[0], $item[1], (bool) $item[2], (int) $item[3]];
    }, $db->query($sql)->fetchAll(\PDO::FETCH_NUM));

    $sparql_query = <<<QUERY
      SELECT ?graph ?entityId
      FROM NAMED <http://joinup.eu/collection/published>
      FROM NAMED <http://joinup.eu/collection/draft>
      FROM NAMED <http://joinup.eu/solution/published>
      FROM NAMED <http://joinup.eu/solution/draft>
      FROM NAMED <http://joinup.eu/asset_release/published>
      FROM NAMED <http://joinup.eu/asset_release/draft>
      FROM NAMED <http://joinup.eu/asset_distribution/published>
      FROM NAMED <http://joinup.eu/asset_distribution/draft>
      WHERE {
        GRAPH ?graph {
          ?entityId ?predicate ?value .
          FILTER(?predicate IN (<http://www.w3.org/ns/adms#versionNotes>, <http://purl.org/dc/terms/description>)) .
          FILTER(REGEX(STR(?value), 'preview_thumbnail|data-entity-type="file"', "i")) .
        }
      }
      QUERY;
    foreach ($sparql->query($sparql_query) as $row) {
      $graph_id = str_ends_with((string) $row->graph, '/draft') ? 'draft' : 'default';
      $sandbox['tasks']['rdf_entity'][] = [$graph_id, (string) $row->entityId];
    }

    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['tasks']['node']) + count($sandbox['tasks']['paragraph']) + count($sandbox['tasks']['rdf_entity']);
  }

  $type = array_key_first($sandbox['tasks']);
  $tasks = array_splice($sandbox['tasks'][$type], 0, 100);
  if (empty($sandbox['tasks'][$type])) {
    unset($sandbox['tasks'][$type]);
  }

  foreach ($tasks as $task) {
    $item = [
      'type' => $type,
      'task' => $task,
    ];
    $queue->createItem($item);
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Queued {$sandbox['count']} out of {$sandbox['max']} text blobs.";
}
