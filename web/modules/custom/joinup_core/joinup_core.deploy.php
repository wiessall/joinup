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
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Fix glossary link for solution entities.
 */
function joinup_core_deploy_109200(array &$sandbox = []): string {
  /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $solutionStorage */
  $solutionStorage = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity');

  /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $menuLinkContentStorage */
  $menuLinkContentStorage = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content');

  if (empty($sandbox['ids'])) {
    $solutionIds = $solutionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('rid', 'solution')
      ->execute();

    $sandbox['ids'] = array_values($solutionIds);
    $sandbox['max'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
  }

  $ids = array_splice($sandbox['ids'], 0, 100);

  /** @var \Drupal\solution\Entity\SolutionInterface $solution */
  foreach ($solutionStorage->loadMultiple($ids) as $solution) {
    $menuIds = $menuLinkContentStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'ogmenu-', 'STARTS_WITH')
      ->condition('title', 'Glossary')
      ->condition('link.uri', 'route:entity.rdf_entity.glossary', 'STARTS_WITH')
      ->condition('link.uri', UriEncoder::encodeUrl($solution->id()), 'ENDS_WITH')
      ->execute();

    if (empty($menuIds)) {
      continue;
    }

    /** @var Drupal\menu_link_content\Entity\MenuLinkContent $menuLinkContent */
    $menuLinkContent = $menuLinkContentStorage->load(reset($menuIds));
    if (!$menuLinkContent) {
      continue;
    }

    $menuLinkContent->link->uri = str_replace(
      UriEncoder::encodeUrl($solution->id()),
      UriEncoder::encodeUrl($solution->getCollection()->id()),
      $menuLinkContent->link->uri
    );
    $menuLinkContent->save();
  }

  $sandbox['current'] += count($ids);
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Updated {$sandbox['current']} of {$sandbox['max']} glossary menu links.";
}

/**
 * Update the 'show archived' for the group content fields.
 */
function joinup_core_deploy_109201(): void {
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
function joinup_core_deploy_109202(): void {
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

/**
 * Add state value to custom page nodes.
 */
function joinup_core_deploy_109203(?array &$sandbox = []): string {
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
