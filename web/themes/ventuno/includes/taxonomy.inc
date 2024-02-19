<?php

/**
 * @file
 * Taxonomy specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_taxonomy_term_alter(array &$suggestions, array $variables): void {
  _ventuno_theme_suggestions_alter($suggestions, $variables);
}

/**
 * Implements hook_preprocess_field().
 */
function ventuno_preprocess_taxonomy_term(array &$variables): void {
  if ($variables['view_mode'] === 'eif_recommendation') {
    $parts = explode(':', $variables['content']['field_eif_related_term'][0]['#plain_text']);
    $title = empty($parts[1]) ? $variables['content']['field_eif_related_term'][0]['#plain_text'] : trim($parts[1]);
    $variables['content']['field_eif_related_term'][0]['#plain_text'] = $title;
  }
}
