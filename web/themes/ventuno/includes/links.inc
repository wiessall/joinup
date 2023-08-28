<?php

/**
 * @file
 * Links specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_links__dropbutton__operations().
 */
function ventuno_preprocess_links__dropbutton__operations(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/button-group';
  // Ensure button-links in paragraphs has the right appearance.
  if ($variables['theme_hook_original'] === 'links__dropbutton__operations__paragraphs') {
    $variables['split'] = TRUE;
  };
  // Override with medium secondary classes.
  if (isset($variables['button']['#options']['attributes']['class'])) {
    $variables['button']['#options']['attributes']['class'] = ['btn', 'btn-secondary'];
  }
}
