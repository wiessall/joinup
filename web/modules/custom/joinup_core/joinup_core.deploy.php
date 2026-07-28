<?php

/**
 * @file
 * Deploy hooks for the Joinup core module.
 */

declare(strict_types=1);

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;

/**
 * Adjust menu links of SEMIC Conference collection.
 */
function joinup_core_deploy_201601(): void {
  // Remove parent of the Overview link.
  $menuTreeStorage = \Drupal::service(MenuTreeStorageInterface::class);
  $menuLinkManager = \Drupal::service(MenuLinkManagerInterface::class);
  $semicConferenceHome = $menuTreeStorage->load('group_link:node-734388-navigation');
  $menuLinkManager->updateDefinition('group_link:overview-730634-navigation', [
    'parent' => '',
    'enabled' => 1,
    'weight' => $semicConferenceHome['weight'],
  ], FALSE);

  // Move children of the custom landing page's menu link under the overview
  // link.
  foreach ($menuTreeStorage->loadAllChildren('group_link:node-734388-navigation') as $definition) {
    $menuLinkManager->updateDefinition($definition['id'], [
      'parent' => 'group_link:overview-730634-navigation',
    ], FALSE);
  }

  // Disable menu link of the custom landing page.
  $menuLinkManager->updateDefinition('group_link:node-734388-navigation', [
    'enabled' => 0,
  ], FALSE);

  // Change "Overview" label to "Home".
  $group = Node::load(730634);
  assert($group instanceof GroupInterface);
  $settings = MetaEntity::loadOrCreate('collection_settings', $group);
  $settings->set('overview_label', 'Home')->save();
}
