<?php

/**
 * @file
 * Image specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_image().
 */
function ventuno_preprocess_image(array &$variables): void {
  $variables['attributes']['loading'] = 'lazy';
}

