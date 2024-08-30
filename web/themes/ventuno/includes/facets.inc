<?php

/**
 * @file
 * Facets specific procedural functions.
 */

declare(strict_types=1);

use Drupal\facets\FacetInterface;

/**
 * Implements hook_preprocess_HOOK().
 *
 * Adds the necessary classes to theme the facet checkbox as a switch.
 */
function ventuno_preprocess_facets_item_list__archived__status(array &$variables): void {
  $variables['attributes']['class'][] = 'list-unstyled';
  foreach ($variables['items'] as $key => $item) {
    $variables['items'][$key]['attributes']['class'][] = 'form-check';
    $variables['items'][$key]['attributes']['class'][] = 'form-switch';
    $variables['items'][$key]['value']['#attributes']['data-drupal-facet-widget-element-class'] = 'form-check-input';
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for facets.
 */
function ventuno_theme_suggestions_facets_item_list_alter(array &$suggestions, array $variables): void {
  $facet = $variables['facet'];
  // Adds a new template in all 'entity_status' except search page.
  if (
    $facet instanceof FacetInterface &&
    $facet->getFieldIdentifier() === 'entity_status' &&
    $facet->id() !== 'status'
  ) {
    $suggestions[] = 'facets_item_list__archived__status';
  }
}
