<?php

/**
 * @file
 * Deploy hooks for the Joinup i18n module.
 */

declare(strict_types=1);

/**
 * Schedule standalone custom pages for translation.
 */
function joinup_i18n_deploy_201500(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  if (!isset($sandbox['nids'])) {
    $sandbox['nids'] = $storage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'custom_page')
      ->condition('status', 1)
      ->notExists('og_audience')
      ->sort('nid')
      ->execute();
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['progress'] = 0;
  }

  $nid = array_shift($sandbox['nids']);
  $sandbox['progress']++;
  tmgmt_content_create_continuous_job_items($storage->load($nid));
  $sandbox['#finished'] = (int) empty($sandbox['nids']);

  return "Scheduled for translation $sandbox[progress] out of $sandbox[total] standalone custom pages";
}
