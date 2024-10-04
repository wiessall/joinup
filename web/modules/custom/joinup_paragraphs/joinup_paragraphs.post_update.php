<?php

/**
 * @file
 * Post-update functions for Joinup Media module.
 */

declare(strict_types=1);

use Drupal\joinup_media\Update\MediaOembedMigrate;

/**
 * Migrates media from paragraph_video_url to remote_video oin video.
 */
function joinup_paragraphs_post_update_111300(array &$sandbox): void {
  /** @var \Drupal\joinup_media\Update\MediaOembedMigrate $update */
  $update = \Drupal::classResolver(MediaOembedMigrate::class);
  $update->migrateParagraphs($sandbox);
}
