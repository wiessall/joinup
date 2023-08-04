<?php

/**
 * @file
 * Facets specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_facets_item_list__checkbox__field_collection_content_status().
 *
 * Adds the necessary classes to theme the facet checkbox as a switch.
 */
function ventuno_preprocess_facets_item_list__archived__status(array &$variables): void {
  $variables['attributes']['class'][] = 'list-unstyled';
  foreach ($variables['items'] as $key => $item) {
    $variables['items'][$key]['attributes']['class'][] = 'form-check form-switch';
    $variables['items'][$key]['value']['#attributes']['data-drupal-facet-widget-element-class'] = 'form-check-input';
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for facets.
 */
function ventuno_theme_suggestions_facets_item_list_alter(array &$suggestions, array $variables): void {
  $archived_facets = [
    'content_listing_status',
    'entity_status',
    'field_is_content_status',
    'field_collection_content_status',
    'user_status',
  ];
  if (isset($variables['attributes']['data-drupal-facet-alias']) && in_array($variables['attributes']['data-drupal-facet-id'], $archived_facets)) {
    $suggestions[] = 'facets_item_list__archived__status';
  }
}
