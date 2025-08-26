<?php

/**
 * @file
 * Post update functions for the Joinup Paragraphs module.
 */

declare(strict_types=1);

use Drupal\joinup_paragraphs\Update\MigrateFileUrl;

/**
 * Implements hook_post_update_NAME().
 */
function joinup_paragraphs_post_update_200601(): string {
  $instance = \Drupal::classResolver(MigrateFileUrl::class);
  $instance->prepareParagraphs();
  $instance->prepareNodeFields();

  return 'Paragraphs structure prepared successfully.';
}

/**
 * Migrate document file_url fields to paragraph fields.
 */
function joinup_paragraphs_post_update_200602(array &$sandbox): string {
  $instance = \Drupal::classResolver(MigrateFileUrl::class);
  $instance->migrateNodeFileUrlField('field_file', 'resources', $sandbox);
  return 'Processed ' . $sandbox['progress'] . ' of ' . $sandbox['total'] . ' document file_url fields.';
}

/**
 * Migrate distribution access_url fields to paragraph fields.
 */
function joinup_paragraphs_post_update_200603(array &$sandbox): string {
  $instance = \Drupal::classResolver(MigrateFileUrl::class);
  $instance->migrateNodeFileUrlField('access_url', 'resources', $sandbox);
  return 'Processed ' . $sandbox['progress'] . ' of ' . $sandbox['total'] . ' distribution access_url fields.';
}

/**
 * Migrate solution/release documentation fields to paragraph fields.
 */
function joinup_paragraphs_post_update_200604(array &$sandbox): string {
  $instance = \Drupal::classResolver(MigrateFileUrl::class);
  $instance->migrateNodeFileUrlField('documentation', 'resources', $sandbox);
  return 'Processed ' . $sandbox['progress'] . ' of ' . $sandbox['total'] . ' solution/release documentation fields.';
}
