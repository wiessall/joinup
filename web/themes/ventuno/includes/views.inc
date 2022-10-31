<?php

/**
 * @file
 * Views specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 *
 * Add views template suggestions.
 */
function ventuno_theme_suggestions_views_view_grid_alter(array &$suggestions, array $variables) {
  $id = $variables['view']->id();
  // Allow template override for glossary view
  if ($id === 'glossary') {
    $suggestions[] = 'views_view_grid__' . $id;
  }
}
