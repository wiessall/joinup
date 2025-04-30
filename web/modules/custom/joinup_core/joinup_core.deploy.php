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
 * Set the group moderation to moderated for solutions.
 */
function joinup_core_deploy_200400(array &$sandbox): string {
  $database = \Drupal::database();
  $database->update('node__group_moderation')
    ->fields(['group_moderation_value' => 1])
    ->condition('bundle', 'solution')
    ->condition('deleted', 0)
    ->execute();

  // Repeat for revisions.
  $database->update('node_revision__group_moderation')
    ->fields(['group_moderation_value' => 1])
    ->condition('bundle', 'solution')
    ->condition('deleted', 0)
    ->execute();

  return 'Updated group moderation for solutions.';
}

/**
 * Grant full membership to all users in all groups.
 */
function joinup_core_deploy_200401(array &$sandbox): string {
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
  $coc_membership_handler = \Drupal::service('code_of_conduct.membership_handler');
  /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
  foreach ($node_storage->loadMultiple($group_ids) as $group) {
    $coc_membership_handler->restoreFullMemberships($group);
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} of groups.";
}

/**
 * Fix wrongly referenced solution_data references.
 */
function joinup_core_deploy_200402(array &$sandbox): string {
  if (empty($sandbox['release_ids'])) {
    $query = \Drupal::database()->select('node_field_data', 'nfd');
    $query->join('node__og_audience', 'og', 'nfd.nid = og.entity_id');
    $query->join('node__solution_data', 'sd', 'nfd.nid = sd.entity_id');
    $query->fields('nfd', ['nid']);
    $query->condition('nfd.type', 'release');
    $query->where('og.og_audience_target_id <> sd.solution_data_target_id');
    $sandbox['release_ids'] = $query->execute()->fetchCol();
    if (empty($sandbox['release_ids'])) {
      return 'No releases to update.';
    }

    $sandbox['max'] = count($sandbox['release_ids']);
    $sandbox['current'] = 0;
  }

  $release_ids = array_splice($sandbox['release_ids'], 0, 50);
  foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($release_ids) as $release) {
    $release->set('solution_data', $release->get('og_audience')->referencedEntities());
    $release->skip_notification = 1;
    $release->save();
  }

  $sandbox['current'] += count($release_ids);
  $sandbox['progress'] = $sandbox['current'] / $sandbox['max'];
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['max'];
  return sprintf('Updated %d of %d releases.', $sandbox['current'], $sandbox['max']);
}
