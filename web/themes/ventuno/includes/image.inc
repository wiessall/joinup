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
  // @todo ISAICP-7491 Ensure images inside content or comments have the "img-fluid" class
  $variables['attributes']['loading'] = 'lazy';
}
