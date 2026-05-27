<?php

/**
 * @file
 * Update hooks for the Joinup Group module.
 */

declare(strict_types=1);

use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLinkDeriver;

/**
 * Convert links from OG menus to group menu links.
 */
function joinup_group_deploy_201300(array &$sandbox): string {
  $db = \Drupal::database();
  $storage = \Drupal::service(MenuTreeStorageInterface::class);

  if (!isset($sandbox['links'])) {
    $query = $db->select('menu_link_content', 'm')
      ->fields('md', ['enabled', 'weight', 'parent', 'id'])
      ->fields('n', ['nid', 'vid', 'title'])
      ->fields('oa', ['bundle'])
      // Only links from OG menus.
      ->condition('md.menu_name', 'ogmenu-%', 'LIKE')
      // Only links to custom pages.
      ->condition('md.link__uri', 'entity:node/%', 'LIKE')
      ->condition('n.type', 'custom_page')
      // Root menu items should come first to be able to solve relationships.
      ->orderBy('md.parent');
    $query->innerJoin('menu_link_content_data', 'md', 'md.id = m.id');
    $query->innerJoin('node_field_data', 'n', 'SUBSTRING(md.link__uri, 13) = n.nid');
    $query->innerJoin('ogmenu_instance__og_audience', 'oa', 'SUBSTRING(md.menu_name, 8) = oa.entity_id');
    // Plugin ID (e.g., menu_link_content:016c320a-b88a-4737-8d12-e0c89a2d14bc).
    $query->addExpression('CONCAT(:base, m.uuid)', 'plugin', [':base' => 'menu_link_content:']);
    // The OG menu instance ID.
    $query->addExpression('SUBSTRING(md.menu_name, 8)', 'ogmenu');
    $sandbox['links'] = $query->execute()->fetchAllAssoc('plugin');

    $sandbox['menu_settings'] = [];
    foreach ($sandbox['links'] as $link) {
      // Convert also the parent if it points to another group menu link.
      if ($link->parent && isset($sandbox['links'][$link->parent])) {
        $parentLink = $sandbox['links'][$link->parent];
        $sandbox['links'][$link->plugin]->parent = GroupMenuLink::id($parentLink->ogmenu . '-' . $parentLink->nid);
      }

      // Build custom page menu settings.
      $sandbox['menu_settings'][$link->nid][$link->bundle] = [
        'ogmenu' => $link->ogmenu,
        'parent' => (string) $sandbox['links'][$link->plugin]->parent,
        'weight' => (int) $link->weight,
        'enabled' => (bool) $link->enabled,
      ];
    }

    $sandbox['links'] = array_values($sandbox['links']);
    $sandbox['total'] = count($sandbox['links']);
    $sandbox['progress'] = 0;
    $sandbox['menu_settings_processed'] = [];
    $sandbox['menu_link_content_processed'] = [];
  }

  $links = array_splice($sandbox['links'], 0, 100);
  $sandbox['progress'] += count($links);

  foreach ($links as $link) {
    // Create 'menu_tree' entry.
    $storage->save([
      'class' => GroupMenuLink::class,
      'derivative' => GroupMenuLinkDeriver::class,
      'menu_name' => "ogmenu-$link->ogmenu",
      'id' => GroupMenuLink::id("$link->ogmenu-$link->nid"),
      'title' => $link->title,
      'route_name' => 'entity.node.canonical',
      'route_parameters' => ['node' => $link->nid],
      'parent' => (string) $link->parent,
      'enabled' => (bool) $link->enabled,
      'weight' => (int) $link->weight,
      'provider' => 'joinup_group',
    ]);

    // Store custom page menu settings.
    if (!isset($sandbox['menu_settings_processed'][$link->nid])) {
      foreach (['node__menu_settings', 'node_revision__menu_settings'] as $table) {
        $db->insert($table)->fields([
          'bundle' => 'custom_page',
          'entity_id' => $link->nid,
          'revision_id' => $link->vid,
          'langcode' => 'en',
          'delta' => 0,
          'menu_settings_value' => serialize($sandbox['menu_settings'][$link->nid]),
        ])->execute();
      }
      $sandbox['menu_settings_processed'][$link->nid][$link->nid] = TRUE;
    }

    // Collect menu link content IDs to delete.
    $sandbox['menu_link_content_processed'][$link->id] = $link->plugin;
  }

  $sandbox['#finished'] = (int) empty($sandbox['links']);

  if ($sandbox['#finished'] === 1) {
    // Delete legacy menu link content links.
    foreach (['', '_data', '_field_revision', '_revision'] as $table) {
      $db->delete("menu_link_content$table")
        ->condition('id', array_keys($sandbox['menu_link_content_processed']), 'IN')
        ->execute();
    }
    // Update the menu tree table.
    $db->delete('menu_tree')
      ->condition('id', array_values($sandbox['menu_link_content_processed']), 'IN')
      ->execute();
    // @todo REMOVE BABEL ENTRIES?
    return 'Cleaned up legacy menu link content links';
  }

  return "Converted $sandbox[progress] out of $sandbox[total] menu links";
}
