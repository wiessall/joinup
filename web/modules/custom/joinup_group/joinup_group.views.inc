<?php

/**
 * @file
 * Views integration for Joinup Group module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data_alter().
 */
function joinup_group_views_data_alter(array &$data): void {
  $data['message']['edit_message__announcement'] = [
    'field' => [
      'title' => t('Link to edit announcement'),
      'help' => t('Provide an edit link the group announcement.'),
      'id' => 'joinup_group_announcement_edit_link',
    ],
  ];

  // Enable entity_reference views filter plugin, used for exposed filter in
  // announcements view.
  $data['message__group']['group_target_id']['filter']['id'] = 'entity_reference';
}
