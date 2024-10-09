<?php

/**
 * @file
 * Post-update functions for Joinup Media module.
 */

declare(strict_types=1);

use Drupal\joinup_media\Update\MediaOembedMigrate;

/**
 * Migrates media video to remote_video.
 */
function joinup_media_post_update_111400(array &$sandbox): void {
  /** @var \Drupal\joinup_media\Update\MediaOembedMigrate $update */
  $update = \Drupal::classResolver(MediaOembedMigrate::class);
  $update->migrateMedia($sandbox);
}

/**
 * Migrates video paragraphs field (paragraph_video_url > remote_video).
 */
function joinup_media_post_update_111401(array &$sandbox): void {
  /** @var \Drupal\joinup_media\Update\MediaOembedMigrate $update */
  $update = \Drupal::classResolver(MediaOembedMigrate::class);
  $update->migrateParagraphs($sandbox);
}
