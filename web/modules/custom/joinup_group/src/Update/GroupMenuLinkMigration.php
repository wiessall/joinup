<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\GroupMenuLinkHelperTrait;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLinkDeriver;

/**
 * Helper class for updating group menu links.
 */
final readonly class GroupMenuLinksMigration implements ContainerInjectionInterface {

  use AutowireTrait;
  use GroupMenuLinkHelperTrait;

  public function __construct(
    protected Connection $db,
    protected MenuTreeStorageInterface $menuTreeStorage,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
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
        $sandbox['links'][$link->legacyPluginId]->parent = GroupMenuLink::buildPluginId('node', $parentLink->customPageNid, $link->ogMenuType);
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
    $this->menuTreeStorage->save([
      'class' => GroupMenuLink::class,
      'derivative' => GroupMenuLinkDeriver::class,
      'menu_name' => $link->menuName,
      'id' => GroupMenuLink::buildPluginId('node', $link->customPageNid, $link->ogMenuType),
      'title' => $link->customPageTitle,
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

  public function processGroupContentLinks(
    array &$sandbox,
    string $linkType,
    string $nodeType,
    string $ogMenuType,
    string $routeName,
    string $title,
    int $weight = 0,
  ): string {
    $storage = $this->entityTypeManager->getStorage('node');

    if (!isset($sandbox['ids'])) {
      // Get the relation between menu_tree.mlid and menu_link_content.id.
      $sql = <<<SQL
        SELECT mt.mlid, m.id
        FROM {menu_tree} mt
        INNER JOIN {menu_link_content} m ON SUBSTRING(mt.id, 19) = m.uuid
        WHERE mt.menu_name LIKE 'ogmenu-%'
        AND mt.route_name = :route
        SQL;
      $ids = $this->db->query($sql, [':route' => $routeName])->fetchAllKeyed();

      // Clean up menu_link_content tables.
      foreach (['', '_data', '_field_revision', '_revision'] as $table) {
        $this->db->delete("menu_link_content$table")
          ->condition('id', array_values($ids), 'IN')
          ->execute();
      }
      // Clean up the menu tree table.
      $this->db->delete('menu_tree')
        ->condition('mlid', array_keys($ids), 'IN')
        ->execute();

      // Pick up the first group content ID from each group.
      $sql = <<<SQL
        SELECT MIN(entity_id)
        FROM {node__og_audience}
        WHERE bundle = :type
        GROUP BY og_audience_target_id
        SQL;
      $sandbox['ids'] = $this->db->query($sql, [':type' => $nodeType])->fetchCol();
      $sandbox['total'] = count($sandbox['ids']);
      $sandbox['progress'] = 0;
    }

    $ids = array_splice($sandbox['ids'], 0, 10);
    $sandbox['progress'] += count($ids);
    foreach ($storage->loadMultiple($ids) as $node) {
      $this->addGroupLink($linkType, $node, $ogMenuType, $routeName, $title, $weight);
    }

    $sandbox['#finished'] = (int) empty($sandbox['ids']);

    return "Created $sandbox[progress] out of $sandbox[total] $nodeType links";
  }

  public function processGroupLinks(
    array &$sandbox,
    string $linkType,
    string $sql,
    string $ogMenuType,
    string $routeName,
    string $title,
    int $weight = 0,
  ): string {
    $storage = $this->entityTypeManager->getStorage('node');

    if (!isset($sandbox['ids'])) {
      // Get the mapping menu_tree.mlid and menu_link_content.id.
      $query = <<<SQL
        SELECT mt.mlid, m.id
        FROM {menu_tree} mt
        INNER JOIN {menu_link_content} m ON SUBSTRING(mt.id, 19) = m.uuid
        WHERE mt.menu_name LIKE 'ogmenu-%'
        AND mt.route_name = :route
        SQL;
      $ids = $this->db->query($query, [':route' => $routeName])->fetchAllKeyed();

      // Clean up menu_link_content tables.
      foreach (['', '_data', '_field_revision', '_revision'] as $table) {
        $this->db->delete("menu_link_content$table")
          ->condition('id', array_values($ids), 'IN')
          ->execute();
      }
      // Clean up the menu tree table.
      $this->db->delete('menu_tree')
        ->condition('mlid', array_keys($ids), 'IN')
        ->execute();

      $sandbox['ids'] = $this->db->query($sql)->fetchCol();
      $sandbox['total'] = count($sandbox['ids']);
      $sandbox['progress'] = 0;
    }

    $ids = array_splice($sandbox['ids'], 0, 10);
    $sandbox['progress'] += count($ids);
    foreach ($storage->loadMultiple($ids) as $nid => $node) {
      assert($node instanceof GroupInterface);
      $ogMenuInstance = $node->getGroupOgMenuInstance($ogMenuType);
      $pluginId = GroupMenuLink::buildPluginId($linkType, $nid, $ogMenuType);
      $this->menuLinkManager->addDefinition($pluginId, [
        'class' => GroupMenuLink::class,
        'deriver' => GroupMenuLinkDeriver::class,
        'provider' => 'joinup_group',
        'menu_name' => "ogmenu-{$ogMenuInstance->id()}",
        'title' => $title,
        'route_name' => $routeName,
        'route_parameters' => ['node' => $nid],
        'weight' => $weight,
      ]);

    }

    $sandbox['#finished'] = (int) empty($sandbox['ids']);

    return "Created $sandbox[progress] out of $sandbox[total] $linkType links";
  }

}
