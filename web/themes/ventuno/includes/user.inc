<?php

/**
 * @file
 * User specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_user().
 */
function ventuno_preprocess_user(array &$variables): void {
  $variables['full_name'] = $variables['user']->getDisplayName();
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_user_alter(array &$suggestions, array $variables): void {
  // Add suggestion based on view mode for user entity.
  if (!empty($variables['elements']['#view_mode'])) {
    $original_theme_hook = $variables['theme_hook_original'];
    $view_mode = $variables['elements']['#view_mode'];
    $suggestions[] = $original_theme_hook . '__' . str_replace('-', '_', $view_mode);
  }
}
