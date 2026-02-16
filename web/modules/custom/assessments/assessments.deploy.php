<?php

/**
 * @file
 * Deploy functions for the assessments module.
 */

declare(strict_types=1);

use Drupal\assessments\WebformSubmissionMigrator;

/**
 * Migrate webform assessment submissions to assessment report entities.
 */
function assessments_deploy_200804(): void {
  $migrator = \Drupal::service(WebformSubmissionMigrator::class);
  $result = $migrator->migrateAll();
  \Drupal::logger('assessments')->info('Webform migration completed: @migrated migrated, @skipped skipped, @failed failed.', [
    '@migrated' => $result['migrated'],
    '@skipped' => $result['skipped'],
    '@failed' => $result['failed'],
  ]);
}
