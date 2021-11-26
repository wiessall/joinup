<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\node\NodeInterface;

/**
 * Remove old "News" page alias in Interoperable Europe collection.
 */
function joinup_core_deploy_0107700(array &$sandbox = []): void {
  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
  $entity_type_manager = \Drupal::entityTypeManager();

  $entities = $entity_type_manager->getStorage('node')
    ->loadByProperties([
      'type'  => 'custom_page',
      'title' => 'News',
    ]);
  if ($entities) {
    /** @var Drupal\node\NodeInterface $node */
    $node = reset($entities);
    if ($node instanceof NodeInterface) {
      \Drupal::service('pathauto.alias_storage_helper')
        ->deleteEntityPathAll($node);
      $node->path->first()->get('pathauto')->purge();
      $node->path = [
        'alias' => '/collection/interoperable-europe/latest-news',
        'pathauto' => 0,
      ];
      $node->save();
      \Drupal::entityTypeManager()
        ->getStorage('node')
        ->resetCache([$node->id()]);
    }
  }
}
