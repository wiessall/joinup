<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

/**
 * Fix orphaned spatial_coverage references to deleted Kosovo term.
 */
function joinup_core_deploy_200800(): string {
  $database = \Drupal::database();

  // Update node__spatial_coverage table.
  $updated_nodes = $database->update('node__spatial_coverage')
    ->fields(['spatial_coverage_target_id' => 33508])
    ->condition('spatial_coverage_target_id', 10106)
    ->execute();

  // Update node_revision__spatial_coverage table.
  $updated_revisions = $database->update('node_revision__spatial_coverage')
    ->fields(['spatial_coverage_target_id' => 33508])
    ->condition('spatial_coverage_target_id', 10106)
    ->execute();

  if ($updated_nodes === 0 && $updated_revisions === 0) {
    return 'No orphaned spatial_coverage references found.';
  }

  return sprintf(
    'Updated %d node field(s) and %d revision field(s) from orphaned Kosovo term (10106) to Kosovo* term (33508).',
    $updated_nodes,
    $updated_revisions
  );
}
