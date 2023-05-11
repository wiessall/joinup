<?php

/**
 * @file
 * Links specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_links__dropbutton__operations().
 */
function ventuno_preprocess_links__dropbutton__operations(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/button-group';
}
