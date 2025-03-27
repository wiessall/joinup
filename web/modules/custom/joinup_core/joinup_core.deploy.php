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
 * Grant full membership to all users in all groups.
 */
function joinup_core_deploy_200400(array &$sandbox): string {
  if (empty($sandbox['group_ids'])) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', ['collection', 'solution'], 'IN')
      ->accessCheck(FALSE);
    $sandbox['group_ids'] = $query->execute();
    if (empty($sandbox['group_ids'])) {
      return 'No groups found.';
    }

    $sandbox['total'] = count($sandbox['group_ids']);
    $sandbox['progress'] = 0;
  }

  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
  $group_ids = array_splice($sandbox['group_ids'], 0, 50);

  /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
  foreach ($node_storage->loadMultiple($group_ids) as $group) {
    $group->restoreFullMemberships();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} of groups.";
}
