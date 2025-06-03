<?php

/**
 * @file
 * Install, update, and uninstall functions for the Joinup solution module.
 */

declare(strict_types=1);

use Drupal\joinup_core\MetaEntityUpdateHelper;

/**
 * Migrates fields from collection to collection_settings meta entity.
 */
function collection_post_update_200502(array &$sandbox): string {
  return MetaEntityUpdateHelper::migrateSettingsFields('collection', $sandbox);
}
