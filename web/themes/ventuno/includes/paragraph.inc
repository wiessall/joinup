<?php

/**
 * @file
 * Paragraph specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__configurable_listing(&$variables) {
  $paragraph = $variables['paragraph'];
  $variables['style'] = $paragraph->get('list_style')->value;
}
