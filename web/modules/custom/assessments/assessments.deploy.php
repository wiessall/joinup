<?php

/**
 * @file
 * Install, update, and uninstall functions for the Assessments module.
 */

declare(strict_types=1);

/**
 * Repoints assessment report subscriptions to the 'assessment' entity type.
 *
 * The legacy 'assessment_report' node bundle was replaced by the 'report'
 * bundle of the 'assessment' entity type, so existing subscriptions have to
 * follow it instead of being dropped.
 */
function assessments_deploy_201601(): string {
  $database = \Drupal::database();

  $count = 0;
  foreach (['og_membership__subscription_bundles', 'og_membership_revision__subscription_bundles'] as $table) {
    if (!$database->schema()->tableExists($table)) {
      continue;
    }
    $count += $database->update($table)
      ->fields([
        'subscription_bundles_entity_type' => 'assessment',
        'subscription_bundles_bundle' => 'report',
      ])
      ->condition('subscription_bundles_entity_type', 'node')
      ->condition('subscription_bundles_bundle', 'assessment_report')
      ->execute();
  }

  return sprintf('Repointed %d assessment report subscriptions to the "assessment" entity type.', $count);
}
