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
 * Set the moderation of solutions to moderated.
 */
function joinup_core_deploy_200400(array &$sandbox): string {
  if (empty($sandbox['solution_ids'])) {
    $query = \Drupal::database()->select('node__group_moderation', 'gm');
    $query->addField('gm', 'entity_id');
    $query->condition('gm.group_moderation_value', 0);
    $query->condition('gm.bundle', 'solution');
    $query->condition('gm.deleted', 0);
    $solution_ids = $query->execute()->fetchCol();
    if (empty($solution_ids)) {
      return 'No solutions to update.';
    }
    $sandbox['solution_ids'] = $solution_ids;
    $sandbox['max'] = count($solution_ids);
    $sandbox['count'] = 0;
  }

  $solution_ids = array_splice($sandbox['solution_ids'], 0, 50);
  foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($solution_ids) as $solution) {
    $solution->set('group_moderation', 1);
    $solution->skip_notification = 1;
    $solution->save();
  }

  $sandbox['count'] += count($solution_ids);
  $sandbox['#finished'] = empty($sandbox['solution_ids']) ? 1 : 0;
  $sandbox['#message'] = t('Updated @count solutions.', ['@count' => count($solution_ids)]);
  $sandbox['#progress'] = $sandbox['count'] / $sandbox['max'];
  return sprintf('Updated %d out of %d solutions.', $sandbox['count'], $sandbox['max']);
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
