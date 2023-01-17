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
