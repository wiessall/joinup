<?php

/**
 * @file
 * Deploy hooks for the joinup_solution module.
 */

declare(strict_types=1);

/**
 * Migrate pending queue items from solution-specific to generic group queue.
 */
function joinup_solution_deploy_201300(): void {
  $db = \Drupal::database();

  $rows = $db->select('queue', 'q')
    ->fields('q', ['item_id', 'data'])
    ->condition('name', 'solution_archive_related_content')
    ->execute()
    ->fetchAll();

  foreach ($rows as $row) {
    $data = unserialize($row->data, ['allowed_classes' => FALSE]);
    if (!is_array($data) || !isset($data['solution']) || isset($data['group'])) {
      continue;
    }
    $data['group'] = $data['solution'];
    unset($data['solution']);
    $db->update('queue')
      ->fields([
        'name' => 'group_archive_related_content',
        'data' => serialize($data),
      ])
      ->condition('item_id', $row->item_id)
      ->execute();
  }
}
