<?php

/**
 * @file
 * Inline Entity Form specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_preprocess_inline_entity_form_entity_table().
 */
function ventuno_preprocess_inline_entity_form_entity_table(array &$variables): void {
  if ($variables['user']->isAuthenticated()) {
    $variables['#attached']['library'][] = 'ventuno/ief-entity-table';
  }
}
