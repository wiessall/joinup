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
use Drupal\search_api\Entity\Index;
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
          'weight' => 1,
          'region' => 'top',
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
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['ids'])) {
    $query = $node_storage->getQuery()->accessCheck(FALSE);
    $query
      ->condition('type', 'custom_page')
      ->notExists('field_state');

    $sandbox['ids'] = $query->execute();
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;
  }

  // Load used indexes.
  $index_published = Index::load('published');
  $index_unpublished = Index::load('unpublished');

  // Start batch tracking for both used indexes.
  $index_published->startBatchTracking();
  $index_unpublished->startBatchTracking();

  $ids = array_splice($sandbox['ids'], 0, 50);
  /** @var \Drupal\custom_page\Entity\CustomPageInterface $node */
  foreach ($node_storage->loadMultiple($ids) as $node) {
    if ($node->isPublished()) {
      $node->set('field_state', 'published');
    }
    else {
      $node->set('field_state', 'unpublished');
    }

    $node->skip_notification = TRUE;
    $node->save();
  }

  // Stop batch tracking for both used indexes.
  $index_published->stopBatchTracking();
  $index_unpublished->stopBatchTracking();

  $sandbox['progress'] += count($ids);
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Processed {$sandbox['progress']} out of {$sandbox['total']} custom pages";
}

/**
 * Update the user__field_user_content values.
 */
function joinup_core_deploy_109304(): void {
  \Drupal::database()->update('user__field_user_content')
    ->fields(['field_user_content_value' => 'a:5:{s:6:"fields";a:2:{s:19:"user_content_bundle";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}s:19:"user_content_status";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:0:"";s:5:"limit";i:12;s:13:"show_archived";s:3:"all";}'])
    ->execute();
}
