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
  $variables['#attached']['library'][] = 'ventuno/content-tables';
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__configurable_listing(&$variables) {
  $paragraph = $variables['paragraph'];
  $variables['style'] = $paragraph->get('list_style')->value;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__accordion(array &$variables): void {
  // This is a workaround to replace the library from the formatter of the
  // accordion paragraph formatter. This is needed because the formatter is set
  // on the field level and currently we need it because Joinup theme is still
  // active.
  //
  // @todo Remove this workaround when ISAICP-7138 along with the template.
  // @see paragraphs-jquery-ui-accordion-formatter.html.twig.
  if (isset($variables['content']['field_accordion_item'][0]['accordion']['#attached']['library'])) {
    $variables['content']['field_accordion_item'][0]['accordion']['#attached']['library'] = 'ventuno/accordion';
  }
  $variables['content']['field_accordion_item'][0]['accordion']['#attributes']['class'][] = 'accordion';
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
