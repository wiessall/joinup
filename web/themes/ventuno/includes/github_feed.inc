<?php

/**
 * @file
 * Github feed specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;

/**
 * Implements hook_preprocess_github_feed_comments().
 */
function ventuno_preprocess_github_feed_comments(array &$variables): void {
  $variables['accordion_item_id'] = Html::getUniqueId('Comments');
  $variables['comments']['#attributes']['class'] = ['list-unstyled', 'mb-0'];
}
