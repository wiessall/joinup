<?php

/**
 * @file
 * Post-update functions for Joinup Media module.
 */

declare(strict_types=1);

use Drupal\joinup_media\Update\MediaOembedMigrate;

/**
 * Migrates media videos to oEmbed.
 */
function joinup_media_post_update_111300(array &$sandbox): void {
  /** @var \Drupal\joinup_media\Update\MediaOembedMigrate $update */
  $update = \Drupal::classResolver(MediaOembedMigrate::class);
  $update->run($sandbox);
}
