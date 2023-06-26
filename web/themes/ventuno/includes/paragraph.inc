<?php

/**
 * @file
 * Paragraph specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_paragraph().
 */
function ventuno_preprocess_paragraph(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/paragraph';
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__configurable_listing(&$variables) {
  $paragraph = $variables['paragraph'];
  // For the configurable listing, the view mode is affected by the type of the
  // listing. Alter the view mode for the listing results based on the
  // 'list_style' field of the paragraph.
  $style = $paragraph->get('list_style')->value;
  $view_mode_mapping = [
    'grid' => 'view_mode_tile',
    'list' => 'view_mode_tile_horizontal',
  ];

  $results = &$variables['content']['content_listing']['#results'];
  foreach ($results as &$result) {
    $result['#item']['#view_mode'] = $view_mode_mapping[$style];
  }
  $variables['style'] = $paragraph->get('list_style')->value;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__accordion_item(array &$variables): void {
  $entity = $variables['paragraph'];
  if ($entity->bundle() !== 'accordion_item') {
    return;
  }
  $parent = $entity->getParentEntity();
  if ($parent->bundle() !== 'accordion') {
    return;
  }
  $variables['parent_id'] = $parent->id();
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraphs_jquery_ui_accordion_formatter(&$variables) {
  $entity = $variables['content']['#object'];
  if ($entity->bundle() !== 'accordion_item') {
    return;
  }
  $parent = $entity->getParentEntity();
  if ($parent->bundle() !== 'accordion') {
    return;
  }

  $variables['parent_id'] = $parent->id();
}
