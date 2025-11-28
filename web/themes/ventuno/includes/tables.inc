<?php

/**
 * @file
 * Tables specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Template\Attribute;

/**
 * Implements hook_preprocess_HOOK() for table--file-formatter-table.html.twig.
 *
 * Extracts the file extension and makes it available on the row level.
 */
function ventuno_preprocess_table__file_formatter_table(array &$variables): void {
  if (!empty($variables['rows'])) {
    foreach ($variables['rows'] as $index => $row) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $variables['rows'][$index]['cells'][0]['content']['#file'];
      $variables['rows'][$index]['#file_extension'] = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    }
  }
}

/**
 * Implements hook_preprocess_HOOK() for table.html.twig.
 *
 * Theme specific tables.
 */
function ventuno_preprocess_table(array &$variables): void {
  // Theme the table showing topics in
  // /admin/structure/entityqueue/homepage_topics/homepage_topics.
  if (array_key_exists('id', $variables['attributes']) && $variables['attributes']['id'] === 'items-values') {
    unset($variables['header']);
    $variables['attributes']['class'][] = 'form-table';
  }

  // Allow passing header_attributes.
  /** @see \ventuno_preprocess_field_multiple_value_form */
  $variables['header_attributes'] = new Attribute($variables['header_attributes']);
}
