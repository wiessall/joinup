<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLinkDeriver;

/**
 * Helper class for updating group menu links.
 */
final readonly class GroupMenuLinksMigration implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected Connection $db,
    protected MenuTreeStorageInterface $menuTreeStorage,
  ) {}

  public function buildSandboxData(array &$sandbox): void {
    if (isset($sandbox['links'])) {
      return;
    }

    $sandbox['start'] = new \DateTimeImmutable();

    $sql = <<<SQL
      SELECT
        md.enabled, md.weight, md.parent, md.id AS menuLinkContentId, md.menu_name AS menuName,
        custom_page.nid AS customPageNid, custom_page.vid AS customPageVid, custom_page.title AS customPageTitle, oa.bundle AS customPageType,
        oa.entity_id AS ogMenuInstanceId, oa.bundle AS ogMenuType, oa.og_audience_target_id AS groupNid,
        mt.id AS legacyPluginId, mt.route_name AS routeName, mt.route_parameters AS routeParameters
      FROM {menu_link_content} m
        INNER JOIN {menu_link_content_data} md ON md.id = m.id
        INNER JOIN {ogmenu_instance__og_audience} oa ON SUBSTRING(md.menu_name, 8) = oa.entity_id
        INNER JOIN {menu_tree} mt ON CONCAT(:base, m.uuid) = mt.id
        LEFT JOIN {node_field_data} custom_page ON SUBSTRING(md.link__uri, 13) = custom_page.nid AND custom_page.type = 'custom_page' AND md.link__uri LIKE 'entity:node/%'
      WHERE md.menu_name LIKE 'ogmenu-%'
        AND (
            (mt.route_name = 'entity.node.canonical' AND custom_page.nid IS NOT NULL)
          -- OR
            -- other link types
        )
      ORDER BY md.parent
      SQL;

    $sandbox['links'] = $this->db->query($sql, [
      ':base' => 'menu_link_content:',
    ])->fetchAllAssoc('legacyPluginId');

    $sandbox['menu_settings'] = [];
    foreach ($sandbox['links'] as $link) {
      // Convert also the parent if it points to another group menu link.
      if ($link->parent && isset($sandbox['links'][$link->parent])) {
        $parentLink = $sandbox['links'][$link->parent];
        $sandbox['links'][$link->legacyPluginId]->parent = GroupMenuLink::buildPluginId($parentLink->customPageNid, $link->ogMenuType);
      }

      // Build custom page menu settings.
      $sandbox['menu_settings'][$link->customPageNid][$link->ogMenuType] = [
        'parent' => (string) $sandbox['links'][$link->legacyPluginId]->parent,
        'weight' => (int) $link->weight,
        'enabled' => (bool) $link->enabled,
      ];
    }

    $sandbox['links'] = array_values($sandbox['links']);
    $sandbox['total'] = count($sandbox['links']);
    $sandbox['progress'] = 0;
    $sandbox['menu_settings_processed'] = [];
  }

  public function processOneLink(\stdClass $link, array &$sandbox): void {
    $this->deleteMenuLinkContent($link);
    $this->createMenuTreeEntry($link);
    $this->storeCustomPageMenuSettings($link, $sandbox);
  }

  protected function deleteMenuLinkContent(\stdClass $link): void {
    foreach (['', '_data', '_field_revision', '_revision'] as $table) {
      $this->db->delete("menu_link_content$table")
        ->condition('id', $link->menuLinkContentId)
        ->execute();
    }
    // Update the menu tree table.
    $this->db->delete('menu_tree')
      ->condition('id', $link->legacyPluginId)
      ->execute();
  }

  protected function createMenuTreeEntry(\stdClass $link): void {
    // Custom page links.
    if (!empty($link->customPageNid)) {
      $id = $link->customPageNid;
      $title = $link->customPageTitle;
    }
//    // Discussions links.
//    elseif ($link->routeName === 'view.discussions.overview') {
//      $id = "discussions-$link->groupNid";
//      $title = 'Discussions';
//    }
//    else {
//      $a =1;
//    }

    // Create 'menu_tree' entry.
    $this->menuTreeStorage->save([
      'class' => GroupMenuLink::class,
      'derivative' => GroupMenuLinkDeriver::class,
      'menu_name' => $link->menuName,
      'id' => GroupMenuLink::buildPluginId($id, $link->ogMenuType),
      'title' => $title,
      'route_name' => $link->routeName,
      'route_parameters' => unserialize($link->routeParameters, ['allowed_classes' => FALSE]),
      'parent' => (string) $link->parent,
      'enabled' => (bool) $link->enabled,
      'weight' => (int) $link->weight,
      'provider' => 'joinup_group',
    ]);
  }

  protected function storeCustomPageMenuSettings(\stdClass $link, array &$sandbox): void {
    if (empty($link->customPageNid) || isset($sandbox['menu_settings_processed'][$link->customPageNid])) {
      return;
    }

    foreach (['node__menu_settings', 'node_revision__menu_settings'] as $table) {
      $this->db->insert($table)->fields([
        'bundle' => 'custom_page',
        'entity_id' => $link->customPageNid,
        'revision_id' => $link->customPageVid,
        'langcode' => 'en',
        'delta' => 0,
        'menu_settings_value' => serialize($sandbox['menu_settings'][$link->customPageNid]),
      ])->execute();
    }
    $sandbox['menu_settings_processed'][$link->customPageNid] = TRUE;
  }
}
