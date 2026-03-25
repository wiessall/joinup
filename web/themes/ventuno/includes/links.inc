<?php

/**
 * @file
 * Links specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_preprocess_links__language_block().
 */
function ventuno_preprocess_links__language_block(array &$variables): void {
  $current = \Drupal::languageManager()->getCurrentLanguage();
  $variables['current_language'] = [
    'code' => $current->getId(),
    'name' => $current->getName(),
  ];

  foreach ($variables['links'] as $langcode => &$link) {
    $link['code'] = $langcode;
  }
}

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
  }
  // Override with medium secondary classes.
  if (isset($variables['button']['#options']['attributes']['class'])) {
    $classes = [
      'btn',
      'btn-secondary',
    ];
    if (in_array('use-ajax', $variables['button']['#options']['attributes']['class'])) {
      $classes[] = 'use-ajax';
    }
    $variables['button']['#options']['attributes']['class'] = $classes;
  }
}
