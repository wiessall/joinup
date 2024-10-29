<?php

/**
 * @file
 * Post-update functions for Joinup Media module.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\joinup_media\Update\MediaOembedMigrate;
use Drupal\user\Entity\Role;

/**
 * Migrates media video to remote_video.
 */
function joinup_media_post_update_111400(array &$sandbox): string {
  /** @var \Drupal\joinup_media\Update\MediaOembedMigrate $update */
  $update = \Drupal::classResolver(MediaOembedMigrate::class);
  return $update->migrateMedia($sandbox);
}

/**
 * Migrates video paragraphs field (paragraph_video_url > remote_video).
 */
function joinup_media_post_update_111401(array &$sandbox): string {
  /** @var \Drupal\joinup_media\Update\MediaOembedMigrate $update */
  $update = \Drupal::classResolver(MediaOembedMigrate::class);
  return $update->migrateParagraphs($sandbox);
}

/**
 * Uninstall video_embed_field module.
 */
function joinup_media_post_update_111402(): void {
  // Delete old fields (preventing uninstall).
  FieldConfig::loadByName('media', 'video', 'video')->delete();
  FieldConfig::loadByName('paragraph', 'video', 'paragraph_video_url')->delete();

  // Delete permissions (preventing uninstall).
  $all_permissions = array_keys(\Drupal::service('user.permissions')->getPermissions());
  foreach (Role::loadMultiple() as $role) {
    // Revoke old video_embed_field permission.
    $role->revokePermission('never autoplay videos');

    // Remove invalid permissions preventing save.
    $role_permissions = $role->getPermissions();
    $invalid_permissions = array_diff($role_permissions, $all_permissions);
    foreach ($invalid_permissions as $permission) {
      $role->revokePermission($permission);
    }

    $role->save();
  }

  // Uninstall video_embed_field.
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
  $module_installer = \Drupal::service('module_installer');
  $module_installer->uninstall(['video_embed_field']);
}
