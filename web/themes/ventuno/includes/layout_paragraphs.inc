<?php

/**
 * @file
 * Layout paragraphs specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_layout_paragraphs_builder_component_menu().
 */
function ventuno_preprocess_layout_paragraphs_builder_component_menu(array &$variables): void {
  foreach ($variables['types']['layout'] as $type) {
    /** @var \Drupal\Core\Template\Attribute $attributes */
    $attributes = $type['link_attributes'];
    $attributes->addClass(['d-inline-flex', 'w-auto', 'my-2']);
  }
  foreach ($variables['types']['content'] as $type) {
    /** @var \Drupal\Core\Template\Attribute $attributes */
    $attributes = $type['link_attributes'];
    $attributes->addClass(['d-inline-flex', 'w-auto', 'my-2']);
  }
}
