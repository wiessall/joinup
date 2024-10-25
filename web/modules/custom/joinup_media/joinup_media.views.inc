<?php

/**
 * @file
 * Views integration for Portal Group module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data_alter().
 */
function joinup_media_views_data_alter(array &$data): void {
  $data['media__og_audience']['og_audience_target_id']['argument']['id'] = 'og_audience_group';
}
