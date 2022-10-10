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

use Drupal\Core\Url;

/**
 * Create a Link to Compatibility Documents from the solution's left-side menu.
 */
function joinup_core_deploy_0108601(array &$sandbox): void {
  $og_menus = \Drupal::entityTypeManager()->getStorage('ogmenu_instance')->loadByProperties([
    'og_audience' => 'http://data.europa.eu/w21/0b18c781-6c88-41ad-b986-63a184693725',
  ]);
  if ($og_menu = reset($og_menus)) {
    $menu_name = 'ogmenu-' . $og_menu->id();
    $menu_link_content_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    $link = [
      'uri' => Url::fromRoute('entity.compatibility_document.collection')->toUriString(),
    ];

    $menu_link_content_storage->create([
      'title' => t('Compatibility Documents'),
      'menu_name' => $menu_name,
      'link' => $link,
      'weight' => -5,
    ])->save();
  }
}
