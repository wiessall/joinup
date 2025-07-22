<?php

/**
 * @file
 * Post update functions for the Joinup Paragraphs module.
 */

declare(strict_types=1);

use \Drupal\joinup_paragraphs\Migrate\PrepareMigration;
use \Drupal\joinup_paragraphs\Migrate\MigrateData;

/**
 * Prepare migration structure.
 */
function joinup_paragraphs_post_update_251000(array &$sandbox): string {
  /** @var \Drupal\joinup_paragraphs\Migrate\PrepareMigration $migration */
  $migration = \Drupal::classResolver(PrepareMigration::class);
  $migration->prepareParagraphs();
  $migration->prepareNodeFields();

  return 'Migration structure preparation completed successfully.';
}

/**
 * Migrate field_file data to resources field for document nodes.
 */
function joinup_paragraphs_post_update_251001(array &$sandbox = NULL): string {
  /** @var \Drupal\joinup_paragraphs\Migrate\MigrateData $migration */
  $migration = \Drupal::classResolver(MigrateData::class);
  return $migration->migrateNodeTypeFields($sandbox, 'document', 'field_file', 'resources');
}
