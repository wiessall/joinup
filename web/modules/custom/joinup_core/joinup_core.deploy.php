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

use Drupal\search_api\Entity\Index;

/**
 * Add state value to custom page nodes.
 */
function joinup_core_deploy_0109200(?array &$sandbox = []): string {
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
