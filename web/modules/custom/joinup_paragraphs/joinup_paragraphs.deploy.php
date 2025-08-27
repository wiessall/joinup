<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

use Drupal\joinup_paragraphs\Update\MigrateFileUrl;

/**
 * Add a no-version suffix to invalid release aliases.
 */
function joinup_paragraphs_deploy_200600(): string {
  $start_time = microtime(TRUE);
  /** @var \Drupal\joinup_paragraphs\Update\MigrateFileUrl $instance */
  $instance = \Drupal::classResolver(MigrateFileUrl::class);
  $instance->deploy();
  $end_time = microtime(TRUE);
  return 'dubi dubi du ' . round($end_time - $start_time, 2) . ' seconds.';
}
