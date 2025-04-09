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
 * Implements hook_deploy_NAME().
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
