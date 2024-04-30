<?php

/**
 * @file
 * Media specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_preprocess_media().
 */
function ventuno_preprocess_media(array &$variables): void {
  $variables['metadata_attributes']['class'] = ['p-2'];
}

/**
 * Implements hook_preprocess_item_list__media_library_add_form_media_list().
 */
function ventuno_preprocess_item_list__media_library_add_form_media_list(array &$variables): void {
  $variables['attributes']['class'][] = 'list-unstyled';
}
