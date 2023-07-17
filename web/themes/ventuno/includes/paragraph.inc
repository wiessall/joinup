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
  $variables['#attached']['library'][] = 'ventuno/paragraph__' . $variables['paragraph']->bundle();
  $variables['attributes']['class'][] = 'paragraph--' . str_replace('_', '-', $variables['paragraph']->bundle());
}

/**
 * Implements hook_preprocess_paragraph().
 */
function ventuno_preprocess_paragraph__accordion(array &$variables): void {
  $variables['attributes']['class'] = ['accordion', 'mb-4'];
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
function ventuno_preprocess_paragraph__accordion_item(array &$variables): void {
  $entity = $variables['paragraph'];
  if ($entity->bundle() !== 'accordion_item') {
    return;
  }
  $parent = $entity->getParentEntity();
  if (!$parent || $parent->bundle() !== 'accordion') {
    return;
  }
  $variables['parent_id'] = $parent->id();
}
