<?php

/**
 * @file
 * Install, update, and uninstall functions for the Joinup user module.
 */

declare(strict_types=1);

/**
 * Replace "individual" source catalogue with "other" in oss solutions.
 */
function joinup_oss_catalogue_post_update_200601(): void {
  $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  $ossSolutionNodes = $nodeStorage
    ->loadByProperties([
      'type' => 'oss_solution',
      'oss_source' => 'custom:individual',
    ]);
  foreach ($ossSolutionNodes as $ossSolutionNode) {
    $ossSolutionNode->set('oss_source', 'custom:other');
    $ossSolutionNode->save();
    $revision_ids = $nodeStorage->revisionIds($ossSolutionNode);
    foreach ($revision_ids as $vid) {
      $revision = $nodeStorage->loadRevision($vid);
      $revision->set('oss_source', 'custom:other');
      $revision->save();
    }
  }
}
