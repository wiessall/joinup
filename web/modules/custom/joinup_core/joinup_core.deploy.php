<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

/**
 * Place a link to the Privacy statement in the footer.
 */
function joinup_core_deploy_0107800(): void {
  // Import the link which has been exported using the Default Content module.
  \Drupal::getContainer()->get('default_content.importer')->importContent('joinup_default_content');

  // Reorder the 'How-to' and 'FAQ' links so they appear below the privacy
  // statement.
  $menu_link_data_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $updates = [
    'b48755b4-8158-4ac4-9f1b-0977ce4fcd13' => -45,
    'f00e66fc-6bfc-416b-88c2-24caa2af0708' => -44,
  ];

  foreach ($updates as $uuid => $weight) {
    $links = $menu_link_data_storage->loadByProperties(['uuid' => $uuid]);
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
    $link = reset($links);
    $link->set('weight', $weight)->save();
  }
}
