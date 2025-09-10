<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

/**
 * Remove documentation menu link content data.
 */
function joinup_menu_deploy_200700(&$sandbox): string {
  $connection = \Drupal::database();
  $limit = 50;

  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    // Get *all* IDs once instead of doing a count + selects.
    $sandbox['ids'] = $connection->select('menu_link_content_data', 'm')
      ->fields('m', ['id'])
      ->condition('link__uri', 'route:joinup_menu.documentation_file%', 'LIKE')
      ->execute()
      ->fetchCol();
    $sandbox['total'] = count($sandbox['ids']);
  }

  if ($sandbox['total'] === 0) {
    $sandbox['#finished'] = 1;
    return 'No documentation links found in OG menu.';
  }

  $batch_ids = array_splice($sandbox['ids'], 0, $limit);

  if (!empty($batch_ids)) {
    $connection->delete('menu_link_content_data')
      ->condition('id', $batch_ids, 'IN')
      ->execute();

    $sandbox['progress'] += count($batch_ids);
  }

  if ($sandbox['progress'] < $sandbox['total']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  }
  else {
    $sandbox['#finished'] = 1;
    \Drupal::service('plugin.manager.menu.link')->rebuild();
    \Drupal::service('router.builder')->rebuild();
  }

  return "Removed {$sandbox['progress']} documentation links from OG menu.";
}
