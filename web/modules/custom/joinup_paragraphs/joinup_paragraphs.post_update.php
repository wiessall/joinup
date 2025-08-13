<?php

/**
 * @file
 * Post update functions for the Joinup Paragraphs module.
 */

declare(strict_types=1);

use Drupal\joinup_paragraphs\Migrate\MigrateData;
use Drupal\joinup_paragraphs\Migrate\PrepareMigration;

/**
 * Prepare migration structure.
 */
function joinup_paragraphs_post_update_200600(array &$sandbox): string {
  $start_time = microtime(TRUE);
  /** @var \Drupal\joinup_paragraphs\Migrate\PrepareMigration $migration */
  $migration = \Drupal::classResolver(PrepareMigration::class);
  $migration->prepareParagraphs();
  $migration->prepareNodeFields();

  $end_time = microtime(TRUE);

  return 'Migration structure preparation completed successfully in ' . round($end_time - $start_time, 2) . ' seconds.';
}

/**
 * Migrate file_url fields to paragraph fields.
 */
function joinup_paragraphs_post_update_200601(): string {
  $start_time = microtime(TRUE);
  /** @var \Drupal\joinup_paragraphs\Migrate\MigrateData $migration */
  $migration = \Drupal::classResolver(MigrateData::class);
  $migration->migrateNodeFileUrlField('field_file', 'resources');
  $end_time = microtime(TRUE);

  return 'Migration of node file_url field to paragraphs field completed successfully in ' . round($end_time - $start_time, 2) . ' seconds.';
}
