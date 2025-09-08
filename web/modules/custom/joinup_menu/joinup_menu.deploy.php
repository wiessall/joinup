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
    $sandbox['total'] = (int) $connection->select('menu_link_content_data', 'm')
      ->condition('title', 'Documentation')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  if ($sandbox['total'] == 0) {
    $sandbox['#finished'] = 1;
    return 'No documentation links found in OG menu.';
  }

  $query = $connection->select('menu_link_content_data', 'm')
    ->fields('m', ['id'])
    ->condition('title', 'Documentation')
    ->range(0, $limit);
  $ids = $query->execute()->fetchCol();

  if (!empty($ids)) {
    $connection->delete('menu_link_content_data')
      ->condition('id', $ids, 'IN')
      ->execute();

    $sandbox['progress'] += count($ids);
  }

  if ($sandbox['progress'] < $sandbox['total']) {
    $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  }
  else {
    $sandbox['#finished'] = 1;
    \Drupal::service('plugin.manager.menu.link')->rebuild();
    \Drupal::service('router.builder')->rebuild();
  }
  return 'Removed ' . $sandbox['progress'] . ' documentation links from OG menu.';
}
