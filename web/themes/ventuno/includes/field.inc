<?php

/**
 * @file
 * Field specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_field_alter(array &$suggestions, array $variables): void {
  // Do not output field labels and wrapping markup for paragraph fields.
  if (isset($variables['element']['#entity_type']) && $variables['element']['#entity_type'] === 'paragraph') {
    // Prepend the new suggestion right after the default field template. This
    // allows overriding single fields, while keeping all the rest markup-free.
    array_unshift($suggestions, 'field__bare');
  }
}

/**
 * Implements hook_preprocess_field_ui_table().
 */
function ventuno_preprocess_field_ui_table(array &$variables): void {
  if ($variables['user']->isAuthenticated()) {
    $variables['#attached']['library'][] = 'ventuno/tabledrag';
  }
}

/**
 * Implements hook_preprocess_field_multiple_value_form().
 */
function ventuno_preprocess_field_multiple_value_form(array &$variables): void {
  if ($variables['user']->isAuthenticated()) {
    $variables['#attached']['library'][] = 'ventuno/tabledrag';
  }
}
