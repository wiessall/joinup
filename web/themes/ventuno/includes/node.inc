<?php

/**
 * @file
 * Node specific procedural functions.
 */

declare(strict_types = 1);

use Drupal\joinup_bundle_class\LogoInterface;

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__numbered_listing(array &$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];

  // Trim the label text.
  $variables['label'] = _ventuno_smart_trim($node->getTitle(), 65);

  // Expose the logo if it exists.
  if ($node instanceof LogoInterface) {
    $field_name = $node->getLogoFieldName();
    if (!empty($variables['content'][$field_name])) {
      $variables['logo'] = $variables['content'][$field_name];
    }
  }

  // Provide date using the date format that is standardized site wide.
  /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
  $date_formatter = \Drupal::service('date.formatter');
  $variables['date'] = $date_formatter->format($node->getCreatedTime(), 'date_only');

  // Provide the content type.
  $variables['type'] = $node->type->entity->label();
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__explore_item(array &$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  // Trim the labels of the items in the Explore block on the homepage down to a
  // manageable size.
  $variables['label'] = _ventuno_smart_trim($node->getTitle(), 45);
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__search_result_featured(array &$variables): void {
  _ventuno_preprocess_search_result($variables, 'node');
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__search_result(array &$variables): void {
  _ventuno_preprocess_search_result($variables, 'node');
}
