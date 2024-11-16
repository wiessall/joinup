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
 * Fix solution nodes having wrong default_langcode after joinup 2.0 migration.
 */
function joinup_core_deploy_200100(): void {
  $database = \Drupal::database();
  $tables = ['node_field_data', 'node_field_revision'];
  foreach ($tables as $table) {
    $database->query("
      UPDATE {{$table}} n
      JOIN (SELECT nid, vid FROM {{$table}} GROUP BY nid, vid HAVING COUNT(*) = 1) AS grouped
      ON n.nid = grouped.nid AND n.vid = grouped.vid
      SET n.default_langcode = 1;
    ");
  }
}

/**
 * Unify the spatial coverage field.
 */
function joinup_core_deploy_200101(array &$sandbox): string {
  $entityTypeManager = \Drupal::entityTypeManager();
  $mappings = [
    'document' => 'field_document_spatial_coverage',
    'news' => 'field_news_spatial_coverage',
    'event' => 'field_event_spatial_coverage',
  ];
  $newField = 'spatial_coverage';

  if (empty($sandbox['nodes'])) {
    $sandbox['progress'] = 0;
    $sandbox['total'] = 0;
    $sandbox['nodes'] = $entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', array_keys($mappings), 'IN')
      ->accessCheck()
      ->execute();
    $sandbox['total'] = count($sandbox['nodes']);
  }
  $nodes = array_splice($sandbox['nodes'], 0, 10);
  foreach ($nodes as $nid) {
    $node = $entityTypeManager->getStorage('node')->load((int) $nid);
    $oldField = $mappings[$node->getType()];
    // Load all revisions of the node.
    $revision_ids = $entityTypeManager->getStorage('node')->revisionIds($node);

    foreach ($revision_ids as $revision_id) {
      $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($revision_id);

      if ($revision->hasField($oldField) && !$revision->get($oldField)->isEmpty()) {
        $revision->set($newField, $revision->get($oldField)->getValue());
      }
      $revision->setNewRevision();
      $revision->save();
    }
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} of nodes.";
}
