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

/**
 * Update the 'show archived' for the group content fields.
 */
function joinup_core_deploy_0109200(): void {
  $graphs = [
    'http://joinup.eu/collection/published',
    'http://joinup.eu/collection/draft',
    'http://joinup.eu/solution/published',
    'http://joinup.eu/solution/draft',
  ];

  $sparql = \Drupal::service('sparql.endpoint');
  foreach ($graphs as $graph) {
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
      if (isset($data['collection_content'])) {
        $data['collection_content']['show_archived'] = 'all';
        $update_query = <<<QUERY
WITH <$graph>
DELETE { ?entity_id <http://joinup.eu/search> ?data }
INSERT { ?entity_id <http://joinup.eu/search> ?new_data }
WHERE {
  ?entity_id a ?type .
  ?entity_id <http://joinup.eu/search> ?data .
}
QUERY;
        $sparql->update($update_query);
      }
    }
  }
}

/**
 * Update the 'show archived' for the paragraph content listing fields.
 */
function joinup_core_deploy_0109201(): void {
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
      $data[0]['show_archived'] = 'all';
      $paragraph->set('content_listing', $data);
      $paragraph->save();
    }
  }
}
