<?php

/**
 * @file
 * Deploy hooks for Joinup i18n module.
 */

declare(strict_types=1);

use Drupal\babel\StringsCollectorFactory;

/**
 * Locale source strings with duplicates.
 */
function joinup_i18n_deploy_201200(): void {
  // Duplicates of sources strings were detected. The root cause couldn't be
  // detected immediately, but the problem is deferred to ISAICP-10740.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-10740
  $db = \Drupal::database();
  $sql = <<<SQL
    SELECT GROUP_CONCAT(lid ORDER BY lid)
    FROM locales_source
    GROUP BY source, context
    HAVING COUNT(source) > 1
    SQL;

  $delete = [];
  foreach ($db->query($sql)->fetchCol() as $grouped) {
    $lids = explode(',', $grouped);
    array_shift($lids);
    $delete = [...$delete, ...$lids];
  }
  if ($delete) {
    $db->delete('locales_source')->condition('lid', $delete, 'IN')->execute();
    $db->delete('locales_location')->condition('sid', $delete, 'IN')->execute();
    $db->delete('locales_target')->condition('lid', $delete, 'IN')->execute();
    $db->delete('babel_source_instance')
      ->condition('plugin', 'locale')
      ->condition('id', $delete, 'IN')
      ->execute();
  }
}

/**
 * Limit language translation to EU languages.
 */
function joinup_i18n_deploy_201201(): void {
  $state = \Drupal::state();
  $disabled = $state->get('joinup_i18n.update_2012');
  if ($disabled !== NULL) {
    $state->delete('joinup_i18n.update_2012');
    if (!empty($disabled)) {
      // Keep disabled, non-EU language strings disabled before this deployment.
      \Drupal::database()->update('babel_source')
        ->fields(['status' => 0])
        ->condition('hash', $disabled, 'IN')
        ->execute();
      \Drupal::service(StringsCollectorFactory::class)->invalidateHashes($disabled);
    }
  }
}
