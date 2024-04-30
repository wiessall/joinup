<?php

/**
 * @file
 * Asset release specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_theme_suggestions_HOOK_alter() for the asset_release_releases_download hook.
 */
function ventuno_theme_suggestions_asset_release_releases_download_alter(array &$suggestions, array $variables): void {
  $original_theme_hook = $variables['theme_hook_original'];
  $suggestions[] = $original_theme_hook;
}
