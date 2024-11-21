<?php

/**
 * @file
 * Views integration for Joinup Media module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data_alter().
 *
 * Use entity reference for group field.
 */
function joinup_media_views_data_alter(array &$data): void {
  $data['media__og_audience']['og_audience_target_id']['filter']['id'] = 'entity_reference';
}
