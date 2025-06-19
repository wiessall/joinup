<?php

/**
 * @file
 * Deploy hooks for Collections module.
 */

declare(strict_types=1);

/**
 * Add collections to the alias updater queue.
 */
function collection_deploy_200500(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['ids'])) {
    // Get all collections.
    $sandbox['ids'] = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'collection')
      ->execute();

    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['ids']);
  }

  $ids = array_splice($sandbox['ids'], 0, 10);

  $groups = $storage->loadMultiple($ids);
  foreach ($groups as $group) {
    \Drupal::getContainer()->get('joinup_group.url_alias_updater')->queueGroupContent($group);
  }

  $sandbox['progress'] += count($ids);
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Processed {$sandbox['progress']} collections out of {$sandbox['total']}";
}
