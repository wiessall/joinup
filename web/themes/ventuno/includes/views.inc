<?php

/**
 * @file
 * Views specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_views_view_grid_alter(array &$suggestions, array $variables): void {
  $id = $variables['view']->id();
  // Add views template suggestions.
  if ($id === 'glossary') {
    $suggestions[] = 'views_view_grid__' . $id;
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for the views_view hook.
 */
function ventuno_theme_suggestions_views_view_alter(array &$suggestions, array $variables): void {
  $view = $variables['view'];
  $id = $view->storage->id();
  $suggestions[] = 'views_view__' . $id;
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for the views_view_unformatted hook.
 */
function ventuno_theme_suggestions_views_view_unformatted_alter(array &$suggestions, array $variables): void {
  $view = $variables['view'];
  $id = $view->storage->id();
  $suggestions[] = 'views_view_unformatted__' . $id;
}
