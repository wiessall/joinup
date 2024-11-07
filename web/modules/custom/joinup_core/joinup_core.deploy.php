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
 * Fix 724664 solution.
 */
function joinup_core_deploy_200100(): void {
  $nid = 724664;
  $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

  if ($node) {
    // Fetch all relevant fields from the node_field_data table.
    $result = \Drupal::database()->select('node_field_data', 'n')
      ->fields('n', [
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
      ->condition('n.nid', $nid)
      ->execute()
      ->fetchAssoc();

    $node->setTitle($result['title']);
    if (!empty($result['created'])) {
      $node->setCreatedTime($result['created']);
    }
    if (!empty($result['changed'])) {
      $node->setChangedTime($result['changed']);
    }
    $node->setPromoted($result['promote']);
    $node->setSticky($result['sticky']);
    $node->set('default_langcode', TRUE);
    $node->setOwnerId($result['uid']);
    $node->set('langcode', $result['langcode']);
    $node->setNewRevision();
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->save();
  }
}
