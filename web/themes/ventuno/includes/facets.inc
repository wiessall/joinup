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
function ventuno_preprocess_facets_item_list__checkbox__field_collection_content_status(array &$variables): void {
  $variables['attributes']['class'][] = 'list-unstyled';
  foreach ($variables['items'] as $key => $item) {
    $variables['items'][$key]['attributes']['class'][] = 'form-check form-switch';
    $variables['items'][$key]['value']['#attributes']['data-drupal-facet-widget-element-class'] = 'form-check-input';
  }
}
