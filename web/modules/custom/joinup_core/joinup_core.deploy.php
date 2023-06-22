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

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\sparql_entity_storage\Entity\Query\Sparql\SparqlArg;

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

/**
 * Update the 'show archived' for the group content fields.
 */
function joinup_core_deploy_109301(): void {
  $graphs = [
    'http://joinup.eu/collection/published' => 'field_collection_content_status',
    'http://joinup.eu/collection/draft' => 'field_collection_content_status',
    'http://joinup.eu/solution/published' => 'field_is_content_status',
    'http://joinup.eu/solution/draft' => 'field_is_content_status',
  ];

  $sparql = \Drupal::service('sparql.endpoint');
  foreach ($graphs as $graph => $field) {
    $query = <<<QUERY
WITH <$graph>
SELECT ?entity_id ?data
WHERE {
  ?entity_id a ?type .
  ?entity_id <http://joinup.eu/search> ?data .
}
QUERY;

    $results = $sparql->query($query);
    foreach ($results as $result) {
      $data = unserialize($result->data->getValue(), [
        'allowed_classes' => FALSE,
      ]);
      $data['show_archived'] = 'all';
      $data['fields'][$field] = [
        'region' => 'top',
        'weight' => 10,
      ];

      $value = SparqlArg::serialize(serialize($data), 'literal');
      $update_query = <<<QUERY
WITH <$graph>
DELETE { ?entity_id <http://joinup.eu/search> ?data }
INSERT { ?entity_id <http://joinup.eu/search> {$value} }
WHERE {
?entity_id a ?type .
?entity_id <http://joinup.eu/search> ?data .
}
QUERY;
      $sparql->update($update_query);
    }
  }
}

/**
 * Update the 'show archived' for the paragraph content listing fields.
 */
function joinup_core_deploy_109302(): void {
  $query = \Drupal::entityQuery('paragraph')
    ->condition('type', [
      'content_listing',
      'carousel',
      'configurable_listing',
    ], 'IN');
  $paragraph_ids = $query->execute();
  $paragraphs = Paragraph::loadMultiple($paragraph_ids);
  foreach ($paragraphs as $paragraph) {
    $data = $paragraph->get('content_listing')->getValue();
    if (!empty($data)) {
      $data[0]['value']['show_archived'] = 'all';
      if ($paragraph->bundle() === 'content_listing') {
        $data[0]['value']['fields']['content_listing_status'] = [
          'region' => 'top',
          'weight' => 10,
        ];
      }
      $paragraph->set('content_listing', $data);
      $paragraph->save();
    }
  }
}

/**
 * Add state value to custom page nodes.
 */
function joinup_core_deploy_109303(?array &$sandbox = []): string {
  $columns = [
    'bundle',
    'entity_id',
    'revision_id',
    'langcode',
    'delta',
    'field_state_value',
  ];
  $db = \Drupal::database();

  if (!isset($sandbox['revisions'])) {
    $query = $db->select('node_field_revision', 'r')
      ->fields('r', ['nid', 'vid', 'status']);
    $query->join('node', 'n', 'r.nid = n.nid');
    $query->addExpression('IF(r.vid = n.vid, 1, 0)', 'current_revision');
    $sandbox['revisions'] = $query
      ->condition('n.type', 'custom_page')
      ->orderBy('r.nid')
      ->orderBy('r.vid')
      ->execute()
      ->fetchAll(\PDO::FETCH_NUM);
    $sandbox['total'] = count($sandbox['revisions']);
    $sandbox['progress'] = 0;
  }

  $revisions = array_splice($sandbox['revisions'], 0, 200);

  $query = $db->insert('node__field_state')->fields($columns);
  $query_revision = $db->insert('node_revision__field_state')->fields($columns);
  foreach ($revisions as $revision) {
    [$nid, $vid, $status, $is_current_revision] = $revision;
    $value = [
      'bundle' => 'custom_page',
      'entity_id' => $nid,
      'revision_id' => $vid,
      'langcode' => 'en',
      'delta' => 0,
      'field_state_value' => $status ? 'published' : 'unpublished',
    ];
    if ($is_current_revision) {
      $query->values($value);
    }
    $query_revision->values($value);
  }
  $query->execute();
  $query_revision->execute();

  $sandbox['progress'] += count($revisions);
  $sandbox['#finished'] = (int) empty($sandbox['revisions']);

  return "Processed {$sandbox['progress']} out of {$sandbox['total']} custom page revisions";
}

/**
 * Update the user__field_user_content values.
 */
function joinup_core_deploy_109304(): void {
  \Drupal::database()->update('user__field_user_content')
    ->fields(['field_user_content_value' => 'a:5:{s:6:"fields";a:2:{s:19:"user_content_bundle";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}s:19:"user_content_status";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:0:"";s:5:"limit";i:12;s:13:"show_archived";s:3:"all";}'])
    ->execute();
}
