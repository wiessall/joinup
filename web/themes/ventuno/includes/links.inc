<?php

/**
 * @file
 * Links specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_links__media_library_menu().
 */
function ventuno_preprocess_links__media_library_menu(array &$variables): void {
  $variables['attributes']['class'][] = 'nav nav-tabs';
  foreach ($variables['links'] as $key => $item) {
    $variables['links'][$key]['link']['#options']['attributes']['class'][] = 'nav-link';
  }
}
