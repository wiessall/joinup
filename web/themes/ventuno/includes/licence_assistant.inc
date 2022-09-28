<?php

/**
 * @file
 * Joinup Licencing Assistant specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_licence_filter_list().
 */
function ventuno_preprocess_licence_filter_list(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/licence-filter';
}
