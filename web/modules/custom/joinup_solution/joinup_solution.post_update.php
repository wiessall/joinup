<?php

/**
 * @file
 * Install, update, and uninstall functions for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\joinup_core\MetaEntityUpdateHelper;

/**
 * Migrates fields from solution to solution_settings meta entity.
 */
function joinup_solution_post_update_200502(array &$sandbox): string {
  return MetaEntityUpdateHelper::migrateSettingsFields('solution', $sandbox);
}
