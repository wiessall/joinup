<?php

/**
 * @file
 * Post-update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example, this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * Standard update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types=1);

/**
 * Move the highlighted content item into the "In the Spotlight" queue.
 */
function joinup_core_post_update_200500(): void {
  $queueStorage = \Drupal::entityTypeManager()->getStorage('entity_subqueue');
  $highlightedContentQueue = $queueStorage->load('highlighted_content');
  $spotlightQueue = $queueStorage->load('spotlight');
  foreach ($highlightedContentQueue->get('items')->referencedEntities() as $entity) {
    $highlightedContentQueue->removeItem($entity);
    $spotlightQueue->addItem($entity);
  }
  $highlightedContentQueue->delete();
  $spotlightQueue->save();
}
