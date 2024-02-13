<?php

/**
 * @file
 * Views specific procedural functions.
 */

declare(strict_types=1);

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

/**
 * Implements hook_theme_suggestions_HOOK_alter() for the views_view_fields hook.
 */
function ventuno_theme_suggestions_views_view_fields_alter(array &$suggestions, array $variables): void {
  $view = $variables['view'];
  $id = $view->storage->id();
  $suggestions[] = 'views_view_fields__' . $id;
}

/**
 * Implements hook_preprocess_views_view_unformatted().
 */
function ventuno_preprocess_views_view_unformatted(array &$variables): void {
  $id = $variables['view']->storage->id();
  if ($id === 'media_library') {
    foreach ($variables['rows'] as $key => $row) {
      $variables['rows'][$key]['attributes']['class'][] = 'col-sm-6 col-lg-3 mb-4';
    }
  }
}

/**
 * Implements hook_preprocess_views_view_table().
 */
function ventuno_preprocess_views_view_table(array &$variables): void {
  $id = $variables['view']->storage->id();
  if ($id === 'media_library') {
    $variables['attributes']['class'][] = 'table table-bordered mt-3';
  }
}

/**
 * Implements hook_preprocess_views_view__media_library().
 */
function ventuno_preprocess_views_view__media_library(array &$variables): void {
  foreach ($variables['header'] as $key => $link) {
    $variables['header'][$key]['#options']['attributes']['class'][] = 'nav-link';
    if (in_array('is-active', $variables['header'][$key]['#options']['attributes']['class'])) {
      $variables['header'][$key]['#options']['attributes']['class'][] = 'active';
    }
  }
}
