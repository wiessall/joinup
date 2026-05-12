<?php

/**
 * @file
 * Views support for Joinup Topic module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data_alter().
 */
function topic_views_data_alter(array &$data): void {
  $data['meta_entity_field_data']['parent'] = [
    'field' => [
      'title' => t('Parent topic'),
      'help' => t('Topic parent.'),
      'id' => 'topic_meta_entity_parent_link',
    ],
  ];
}
