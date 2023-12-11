<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types = 1);

/**
 * Set the published_at to NULL where it currently is 2147483647.
 */
function joinup_core_post_update_110000(array &$sandbox): string {
  $database = \Drupal::database();
  if (empty($sandbox['nodes'])) {
    $query = $database->select('node_field_revision', 'nfr');
    $query->join('node_revision', 'nr', 'nfr.vid = nr.vid');
    $query->fields('nfr', ['nid']);
    $query->addExpression('MAX(nfr.vid)', 'vid');
    $query->addExpression('MAX(nr.revision_timestamp)', 'revision_timestamp');
    $query->condition('nfr.status', 1);
    $query->condition('nfr.published_at', 2147483647);
    $query->groupBy('nfr.nid');

    $sandbox['nodes'] = $query->execute()->fetchAll();
    $sandbox['current'] = 0;
    $sandbox['total'] = count($sandbox['nodes']);
  }

  $limit = 50;
  $nodes = array_slice($sandbox['nodes'], $sandbox['current'], $limit);
  $sandbox['current'] += count($nodes);
  foreach ($nodes as $node) {
    $database->update('node_field_revision')
      ->fields(['published_at' => NULL])
      ->condition('vid', $node->vid, '<')
      ->condition('nid', $node->nid)
      ->execute();
    $database->update('node_field_revision')
      ->fields(['published_at' => $node->revision_timestamp])
      ->condition('vid', $node->vid, '>=')
      ->condition('nid', $node->nid)
      ->execute();
  }
  if ($sandbox['current'] >= $sandbox['total']) {
    // Clean unpublished content published_at date.
    $database->query('UPDATE node_field_revision SET published_at = NULL WHERE published_at = 2147483647');
    // Copy the publication date from the revisions table to the node table.
    $database->query('UPDATE {node_field_data} d, {node_field_revision} r
      SET d.published_at = r.published_at
      WHERE d.vid = r.vid
      AND d.published_at = 2147483647');
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['current'] / $sandbox['total']);
  }
  return "Processed {$sandbox['current']} out of {$sandbox['total']} nodes";
}
