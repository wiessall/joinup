<?php

/**
 * @file
 * Post-update functions for the Assessments module.
 */

declare(strict_types=1);

/**
 * Deletes all assessment report nodes.
 */
function assessments_post_update_201600(): void {
  // Not a deploy hook: config import refuses to drop the 'assessment_report'
  // node type while its content exists, and only post-updates run before it.
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'assessment_report')
    ->accessCheck(FALSE)
    ->execute();

  if (!empty($nids)) {
    $storage->delete($storage->loadMultiple($nids));
  }
}
