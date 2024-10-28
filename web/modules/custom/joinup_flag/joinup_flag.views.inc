<?php

/**
 * @file
 * Contains views API hooks for Interoperable Europe Portal flag module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data_alter().
 */
function joinup_flag_views_data_alter(array &$data): void {
  $data['views']['joinup_flag_flagged_entity'] = [
    'title' => t('Flagged entity view'),
    'field' => [
      'title' => t('Flagged entity view'),
      'id' => 'joinup_flag_flagged_entity',
    ],
  ];
}
