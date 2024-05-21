<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types=1);

/**
 * Clean up OG Menu instances that reference non-existing groups.
 */
function joinup_core_deploy_110600(): string {
  $invalid_group_ids = [
    'http://data.europa.eu/w21/577b6bb4-34ed-4fe8-9981-4b191bd8b237',
    'http://data.europa.eu/w21/c29e7af4-a78f-45e9-bcaa-9792f154f87d',
    'http://data.europa.eu/w21/da7fb874-70b6-4dd9-83f1-a520ad8f8793',
  ];

  $og_menu_instance_storage = \Drupal::entityTypeManager()->getStorage('ogmenu_instance');
  $invalid_og_menu_instances = $og_menu_instance_storage->loadByProperties([
    'og_audience' => $invalid_group_ids,
  ]);

  foreach ($invalid_og_menu_instances as $invalid_og_menu_instance) {
    $invalid_og_menu_instance->delete();
  }

  return 'Removed ' . count($invalid_og_menu_instances) . ' OG Menu instances that reference non-existing groups.';
}
