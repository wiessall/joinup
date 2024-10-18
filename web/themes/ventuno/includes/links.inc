<?php

/**
 * @file
 * Links specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_preprocess_links__media_library_menu().
 */
function ventuno_preprocess_links__media_library_menu(array &$variables): void {
  $variables['attributes']['class'][] = 'nav nav-tabs';
  foreach ($variables['links'] as $key => $item) {
    $variables['links'][$key]['link']['#options']['attributes']['class'][] = 'nav-link';
  }
}

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
    $variables['button']['#options']['attributes']['class'] = [
      'btn',
      'btn-secondary',
      'use-ajax',
    ];
  }
}
