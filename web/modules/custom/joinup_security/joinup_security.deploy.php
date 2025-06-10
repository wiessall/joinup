<?php

/**
 * @file
 * Deploy hooks for Joinup Security module.
 */

declare(strict_types=1);

use Drupal\file_visibility\FileVisibilityInterface;
use Drupal\track_usage\Model\BulkUpdateMethod;
use Drupal\track_usage\Updater;

/**
 * Disable file visibility processing.
 */
function joinup_security_deploy_200500(): void {
  // Temporarily, unset the track usage config use by file visibility.
  \Drupal::configFactory()->getEditable('file_visibility_track_usage.settings')
    ->set('track_usage_config', NULL)
    ->save();
}

/**
 * Store files original URI.
 */
function joinup_security_deploy_200501(): void {
  $db = \Drupal::database();

  $query = $db->select('file_managed', 'f')->fields('f', ['fid', 'uri']);
  $or = $query->orConditionGroup()
    ->condition('uri', 'public://%', 'LIKE')
    ->condition('uri', 'private://%', 'LIKE');
  $query->condition($or);

  $db->insert(FileVisibilityInterface::TABLE)
    ->from($query)
    ->execute();
}

/**
 * Queue usage data build.
 */
function joinup_security_deploy_200502(): void {
  $config = \Drupal::entityTypeManager()->getStorage('track_usage_config')
    ->load('file_visibility');
  \Drupal::service(Updater::class)->update(BulkUpdateMethod::Queue, $config);
}
