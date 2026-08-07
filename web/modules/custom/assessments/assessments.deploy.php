<?php

/**
 * @file
 * Install, update, and uninstall functions for the Assessments module.
 */

declare(strict_types=1);

/**
 * Deletes all assessment report nodes.
 */
function assessments_deploy_201600(): void {
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'assessment_report')
    ->accessCheck(FALSE)
    ->execute();

  if (!empty($nids)) {
    $nodes = $storage->loadMultiple($nids);

    foreach ($nodes as $node) {
      $node->delete();
    }
  }
}
