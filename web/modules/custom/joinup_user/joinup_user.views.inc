<?php

/**
 * @file
 * Provide views data for user.module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data_alter().
 */
function joinup_user_views_data_alter(array &$data): void {
  // Replace the standard views bulk form for users with our customized version.
  $data['users']['user_bulk_form']['field']['id'] = 'joinup_user_bulk_form';

  $data['users']['is_author'] = [
    'title' => t('Has authored content'),
    'help' => t('Returns whether the user has authored content.'),
    'field' => [
      'id' => 'joinup_user_is_author',
    ],
  ];

  $data['users']['group'] = [
    'title' => t('Group(s)'),
    'help' => t('OG groups the user belongs to.'),
    'filter' => [
      'id' => 'joinup_user_group',
      'allow empty' => TRUE,
    ],
  ];
}
