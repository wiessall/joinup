<?php

/**
 * @file
 * Image specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_preprocess_image().
 */
function ventuno_preprocess_image(array &$variables): void {
  // Explicitly sets dimensions to image based on image styles to avoid
  // Cumulative Layout Shift.
  switch ($variables['style_name']) {
    // 'Logo' used on collection and solution headers.
    case 'logo':
      $variables['attributes']['height'] = 100;
      $variables['attributes']['width'] = 100;
      break;
  }
}
