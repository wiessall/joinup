<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types = 1);

/**
 * Remove 'About' from menu.
 */
function joinup_core_post_update_110000(array &$sandbox): string {
  $db = Drupal::database();
  /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager */
  $menuLinkManager = Drupal::service('plugin.manager.menu.link');

  if (!isset($sandbox['data'])) {
    $sandbox['data'] = $db->select('menu_tree', 'mt')
      ->fields('mt', ['id'])
      ->condition('mt.route_name', 'entity.rdf_entity.about_page')
      ->execute()
      ->fetchAll();

    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['data']);
  }

  $spliced = array_splice($sandbox['data'], 0, 300);
  foreach ($spliced as $data) {
    $menuLinkManager->removeDefinition($data->id);

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']}.";
}
