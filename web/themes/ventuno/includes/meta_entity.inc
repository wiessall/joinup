<?php

/**
 * @file
 * Meta Entity specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_meta_entity_alter(array &$suggestions, array $variables): void {
  _ventuno_theme_suggestions_alter($suggestions, $variables);
}
