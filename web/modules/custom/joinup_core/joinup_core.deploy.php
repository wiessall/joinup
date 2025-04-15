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
    $solution->search_api_skip_tracking = 1;
    $solution->save();
  }

  $sandbox['count'] += count($solution_ids);
  $sandbox['#finished'] = empty($sandbox['solution_ids']) ? 1 : 0;
  $sandbox['#message'] = t('Updated @count solutions.', ['@count' => count($solution_ids)]);
  $sandbox['#progress'] = $sandbox['count'] / $sandbox['max'];
  return sprintf('Updated %d out of %d solutions.', $sandbox['count'], $sandbox['max']);
}

/**
 * Fix wrongly referenced solution_data references.
 */
function joinup_core_deploy_200401(array &$sandbox): string {
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
