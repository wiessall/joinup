<?php

/**
 * @file
 * Post update functions for the Joinup Paragraphs module.
 */

declare(strict_types=1);

use Drupal\joinup_paragraphs\Update\MigrateFileUrl;

/**
 * Migrate file_url fields to paragraph fields.
 */
function joinup_paragraphs_post_update_200700(): string {
  $start_time = microtime(TRUE);
  /** @var \Drupal\joinup_paragraphs\Update\MigrateFileUrl $instance */
  $instance = \Drupal::classResolver(MigrateFileUrl::class);
  $instance->run();
  $end_time = microtime(TRUE);
  return 'Migration of node file_url field to paragraphs field completed successfully in ' . round($end_time - $start_time, 2) . ' seconds.';
}
