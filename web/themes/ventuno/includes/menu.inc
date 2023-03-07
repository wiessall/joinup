<?php

/**
 * @file
 * Menu specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_menu_alter(array &$suggestions, array $variables): void {
  // Adds a theme suggestion for the menus rendered in the footer, so they can
  // be styled in the same way.
  if (in_array($variables['menu_name'], [
    'footer',
    'support',
    'footer-about-us',
    'footer-european-commission',
    'footer-follow-us',
    'footer-help-and-support',
  ], TRUE)) {
    $suggestions[] = 'menu__region_footer';
    $suggestions[] = 'menu__region_footer__' . str_replace('-', '_', $variables['menu_name']);
  }
}

/**
 * Implements hook_preprocess_menu_local_action().
 */
function ventuno_preprocess_menu_local_action(array &$variables): void {
  $btn_classes = [
    'btn',
    'btn-md',
    'btn-secondary',
  ];
  $variables['link']['#options']['attributes']['class'] = $btn_classes;
}
