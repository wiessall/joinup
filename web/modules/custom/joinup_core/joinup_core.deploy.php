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
 * Update all solution with default_langcode = 0 and set a new revision.
 */
function joinup_core_deploy_200100(): void {
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');

  $results = \Drupal::database()->select('node_field_data', 'n')
    ->fields('n', [
      'nid',
      'title',
      'created',
      'changed',
      'promote',
      'sticky',
      'default_langcode',
      'status',
      'uid',
      'langcode',
    ])
    ->condition('n.default_langcode', 0)
    ->condition('n.type', 'solution')
    ->execute()
    ->fetchAllAssoc('nid');

  foreach ($results as $nid => $data) {
    // Load the node by its ID.
    $node = $node_storage->load($nid);

    if ($node) {
      $node->setTitle($data->title);
      if (!empty($data->created)) {
        $node->setCreatedTime($data->created);
      }
      if (!empty($data->changed)) {
        $node->setChangedTime($data->changed);
      }

      $node->setPromoted($data->promote);
      $node->setSticky($data->sticky);
      $node->set('default_langcode', TRUE);
      $node->setOwnerId($data->uid);
      $node->set('langcode', $data->langcode);
      $node->setNewRevision();
      $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $node->save();
    }
  }
}
