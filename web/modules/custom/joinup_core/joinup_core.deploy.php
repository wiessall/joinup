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
use Drupal\eupl\Eupl;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\og\OgMembershipInterface;

/**
 * Set announcements_enabled enable in collections.
 */
function joinup_core_deploy_108600(array &$sandbox): string {
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = \Drupal::entityQuery('meta_entity')->condition('type', 'collection_settings')->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $meta_entity_storage = \Drupal::entityTypeManager()->getStorage('meta_entity');
  $ids = array_splice($sandbox['ids'], 0, 50);
  foreach ($meta_entity_storage->loadMultiple($ids) as $meta_entity) {
    $meta_entity->set('announcements_enabled', TRUE);
    $meta_entity->save();
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Updated {$sandbox['count']} out of {$sandbox['max']} meta-entity settings collection.";
}

/**
 * Subscribe group members to announcements.
 */
function joinup_core_deploy_108601(): void {
  \Drupal::database()->update('og_membership')
    ->fields(['announcements' => 1])
    ->condition('type', OgMembershipInterface::TYPE_DEFAULT)
    ->condition('entity_type', 'rdf_entity')
    ->condition('entity_bundle', JoinupGroupHelper::GROUP_BUNDLES, 'IN')
    // Don't filter on whether the user is blocked, or they didn't agree yet to
    // the 'Legal notice', or their membership is pending. These conditions may
    // change in the future, so we default them to subscribe to announcements.
    ->execute();
}

/**
 * Create a Link to Compatibility Documents from the solution's left-side menu.
 */
function joinup_core_deploy_0108602(array &$sandbox): void {
  $og_menus = \Drupal::entityTypeManager()->getStorage('ogmenu_instance')->loadByProperties([
    'og_audience' => Eupl::JLA_SOLUTION,
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
