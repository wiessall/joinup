<?php

/**
 * @file
 * Deploy functions for EIF.
 */

declare(strict_types=1);

/**
 * Delete repeated EIF Perspective links.
 */
function eif_deploy_110700(): void {
  // Retrieve EIF Perspective menu links from the same menu having the same uri.
  $query = \Drupal::database()->select('menu_link_content_data', 'd')
    ->fields('d', ['menu_name'])
    ->condition('d.title', 'EIF Perspective');
  $query->addExpression('GROUP_CONCAT(d.id ORDER BY d.id)', 'ids');
  $query->groupBy('d.menu_name')
    ->having('COUNT(d.id) > 1');
  $links = $query->execute()->fetchAll();

  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  foreach ($links as $link) {
    $ids = explode(',', $link->ids);
    // Keep the first, delete the others.
    array_pop($ids);

    $storage->delete($storage->loadMultiple($ids));
  }
}
