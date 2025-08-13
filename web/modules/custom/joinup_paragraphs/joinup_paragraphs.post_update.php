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
  /** @var \Drupal\joinup_paragraphs\Migrate\PrepareMigration $migration */
  $migration = \Drupal::classResolver(PrepareMigration::class);
  $migration->prepareParagraphs();
  $migration->prepareNodeFields();

  return 'Migration structure preparation completed successfully.';
}

/**
 * Migrate file_url fields to paragraph fields.
 */
function joinup_paragraphs_post_update_200601(): void {
  /** @var \Drupal\joinup_paragraphs\Migrate\MigrateData $migration */
  $migration = \Drupal::classResolver(MigrateData::class);
  $migration->migrateNodeFileUrlField('field_file', 'resources');
}
