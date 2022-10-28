<?php

/**
 * @file
 * Paragraphs specific procedural functions.
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
