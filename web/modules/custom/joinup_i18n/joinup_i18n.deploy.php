<?php

/**
 * @file
 * Deploy hooks for the Joinup i18n module.
 */

declare(strict_types=1);

use Drupal\joinup_i18n\Update\TmgmtRecovery;

/**
 * Repairs field values that tmgmt translated and mangled.
 */
function joinup_i18n_deploy_201501(): void {
  \Drupal::classResolver(TmgmtRecovery::class)->run();
}

/**
 * Schedule standalone custom pages for translation.
 */
function joinup_i18n_deploy_201502(array &$sandbox): string {
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
