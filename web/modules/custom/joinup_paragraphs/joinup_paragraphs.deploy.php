<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

use Drupal\joinup_paragraphs\Update\MigrateFileUrl;

/**
 * Re-generate track_usage data.
 */
function joinup_paragraphs_deploy_200700(): string {
  $start_time = microtime(TRUE);
  /** @var \Drupal\joinup_paragraphs\Update\MigrateFileUrl $instance */
  $instance = \Drupal::classResolver(MigrateFileUrl::class);
  $instance->deploy();
  $end_time = microtime(TRUE);
  return 'Re-generated track_usage data for affected document nodes successfully in ' . round($end_time - $start_time, 2) . ' seconds.';
}
