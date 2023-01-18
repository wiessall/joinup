<?php

/**
 * @file
 * Tables specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_HOOK() for table--file-formatter-table.html.twig.
 *
 * Extracts the file extension and makes it available on the row level.
 */
function ventuno_preprocess_table__file_formatter_table(&$variables) {
  if (!empty($variables['rows'])) {
    foreach ($variables['rows'] as $index => $row) {
      /** @var \Drupal\file\Entity\File $file */
      $file = $variables['rows'][$index]['cells'][0]['content']['#file'];
      $variables['rows'][$index]['#file_extension'] = _ventuno_get_file_extension($file);
    }
  }
}
