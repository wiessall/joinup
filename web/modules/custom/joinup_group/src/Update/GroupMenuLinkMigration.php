<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Update;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\joinup_group\GroupMenuLinkHelperTrait;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;

/**
 * Temporary helper class for updating group menu links.
 */
final readonly class GroupMenuLinkMigration implements ContainerInjectionInterface {

  use AutowireTrait;
  use GroupMenuLinkHelperTrait;

  public function __construct(
    protected Connection $db,
    protected MenuTreeStorageInterface $menuTreeStorage,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Builds sandbox data for group menu links based on custom pages.
   */
  public function buildSandboxData(array &$sandbox): void {
    if (isset($sandbox['links'])) {
      return;
    }

    $sandbox['start'] = new \DateTimeImmutable();

    $query = $this->getBaseQuery('entity.node.canonical');
    $query->innerJoin('node_field_data', 'custom_page', "SUBSTRING(md.link__uri, 13) = custom_page.nid AND custom_page.type = 'custom_page' AND md.link__uri LIKE 'entity:node/%'");
    $query->addExpression('custom_page.nid', 'customPageNid');
    $query->addExpression('custom_page.vid', 'customPageVid');
    $query->addExpression('custom_page.title', 'customPageTitle');
    $query->isNotNull('custom_page.nid');
    $sandbox['links'] = $query->execute()->fetchAllAssoc('legacyPluginId');

    $this->removeMenuLinkContent(array_column($sandbox['links'], 'menuLinkContentId'), array_column($sandbox['links'], 'menuTreeLinkId'));

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

  /**
   * Processes one custom page group menu link.
   */
  public function processOneLink(\stdClass $link, array &$sandbox): void {
    $this->createMenuTreeEntry($link);
    $this->storeCustomPageMenuSettings($link, $sandbox);
  }

  /**
   * Creates a new menu tree entry for the custom page group menu link.
   */
  protected function createMenuTreeEntry(\stdClass $link): void {
    $this->menuTreeStorage->save([
      'class' => GroupMenuLink::class,
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

  /**
   * Stores the custom page menu settings.
   */
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

  /**
   * Converts group content links, such as glossary, release, discussion.
   */
  public function processGroupLinks(array &$sandbox, string $linkType): string {
    $routeName = match ($linkType) {
      'discussion' => 'view.discussions.overview',
      'glossary' => 'entity.node.glossary',
      'release' => 'joinup_release.solution_release.overview',
      'website_link' => 'joinup_menu.website_link',
      'overview' => 'entity.node.canonical',
      'eif_perspective' => 'entity.node.eif_perspective',
    };
    $storage = $this->entityTypeManager->getStorage('node');

    if (!isset($sandbox['items'])) {
      $sandbox['start'] = new \DateTimeImmutable();

      $query = $this->getBaseQuery($routeName);

      // Get a list of groups to limit the list of links to.
      $groupIds = $this->limitToGroupIds($linkType);
      if ($groupIds !== NULL) {
        $query->condition('oa.og_audience_target_id', $groupIds, 'IN');
      }
      $sandbox['items'] = $query->execute()->fetchAll(FetchAs::Associative);
      $sandbox['total'] = count($sandbox['items']);
      $sandbox['progress'] = 0;

      // Remove legacy menu links.
      $this->removeMenuLinkContentByRoute($routeName);
    }

    $items = array_splice($sandbox['items'], 0, 10);
    $sandbox['progress'] += count($items);
    $groups = $storage->loadMultiple(array_column($items, 'groupNid'));

    foreach ($items as $item) {
      $this->addLink(
        linkType: $linkType,
        group: $groups[$item['groupNid']],
        ogMenuType: $item['ogMenuType'],
        title: $item['linkTitle'],
        routeName: $routeName,
        parent: (string) $item['parent'],
        enabled: (bool) $item['enabled'],
        weight: (int) $item['weight'],
      );
    }

    $sandbox['#finished'] = (int) empty($sandbox['items']);

    if ($sandbox['#finished'] === 1) {
      return "Finished converting $sandbox[total] $linkType links. Took " . $this->formatInterval($sandbox['start']);
    }

    return "Converted $sandbox[progress] out of $sandbox[total] $linkType links";
  }

  /**
   * Removes menu link content for a given route name.
   */
  public function removeMenuLinkContentByRoute(string $routeName, ?bool $enabled = NULL): void {
    // Get the mapping between menu_tree.mlid and menu_link_content.id.
    $query = $this->db->select('menu_tree', 'mt')
      ->fields('mt', ['mlid'])
      ->fields('m', ['id'])
      ->condition('mt.menu_name', 'ogmenu-%', 'LIKE')
      ->condition('mt.route_name', $routeName);
    $query->innerJoin('menu_link_content', 'm', 'SUBSTRING(mt.id, 19) = m.uuid');

    if ($enabled !== NULL) {
      $query->condition('mt.enabled', (int) $enabled);
    }

    $ids = $query->execute()->fetchAllKeyed();

    $this->removeMenuLinkContent(array_values($ids), array_keys($ids));
  }

  /**
   * Removes menu link content for a given list of IDs.
   */
  public function removeMenuLinkContent(array $menuLinkContentIds, array $menuTreeLinkIds): void {
    // Clean up menu_link_content tables.
    foreach (['', '_data', '_field_revision', '_revision'] as $table) {
      $this->db->delete("menu_link_content$table")
        ->condition('id', $menuLinkContentIds, 'IN')
        ->execute();
    }
    // Clean up the menu tree table.
    $this->db->delete('menu_tree')
      ->condition('mlid', $menuTreeLinkIds, 'IN')
      ->execute();
  }

  /**
   * Returns a base query to retrieve legacy data.
   */
  public function getBaseQuery(string $routeName): SelectInterface {
    $query = $this->db->select('menu_link_content', 'm')
      ->fields('md', ['enabled', 'weight', 'parent'])
      ->condition('mt.id', 'menu_link_content:%', 'LIKE')
      ->condition('mt.menu_name', 'ogmenu-%', 'LIKE')
      ->condition('mt.route_name', $routeName);

    $query->innerJoin('menu_link_content_data', 'md', 'md.id = m.id');
    $query->innerJoin('ogmenu_instance__og_audience', 'oa', 'SUBSTRING(md.menu_name, 8) = oa.entity_id');
    $query->innerJoin('menu_tree', 'mt', 'CONCAT(:base, m.uuid) = mt.id', [':base' => 'menu_link_content:']);

    $query->addExpression('md.id', 'menuLinkContentId');
    $query->addExpression('md.menu_name', 'menuName');
    $query->addExpression('md.title', 'linkTitle');
    $query->addExpression('oa.entity_id', 'ogMenuInstanceId');
    $query->addExpression('oa.bundle', 'ogMenuType');
    $query->addExpression('oa.og_audience_target_id', 'groupNid');
    $query->addExpression('mt.id', 'legacyPluginId');
    $query->addExpression('mt.route_name', 'routeName');
    $query->addExpression('mt.route_parameters', 'routeParameters');
    $query->addExpression('mt.mlid', 'menuTreeLinkId');
    $query->orderBy('md.parent');

    return $query;
  }

  /**
   * Returns the formatted time elapsed since a given start time.
   */
  public function formatInterval(\DateTimeImmutable $start): string {
    $stop = new \DateTimeImmutable();
    $diff = $stop->diff($start);
    return $diff->format('%i min, %s.%F sec');
  }

  /**
   * Specific SQL queries for group links.
   */
  private function limitToGroupIds(string $linkType): ?array {
    switch ($linkType) {
      case 'discussion':
      case 'release':
      case 'glossary':
        // Get groups having $linkType children.
        $groupIds = $this->db->select('node__og_audience', 'oa')
          ->fields('oa', ['og_audience_target_id'])
          ->condition('bundle', $linkType)
          ->groupBy('og_audience_target_id')
          ->execute()
          ->fetchCol();

        if ($linkType === 'glossary') {
          // Glossary is a special case because it creates also links in
          // solutions, which are targeting the collection link. We add also the
          // solutions to the list of groups.
          $groupIds = [
            ...$groupIds,
            ...$this->db->select('node__og_audience', 'oa')
              ->fields('oa', ['entity_id'])
              ->condition('bundle', 'solution')
              ->condition('og_audience_target_id', $groupIds, 'IN')
              ->execute()
              ->fetchCol(),
          ];
        }

        return $groupIds;

      case 'website_link':
        $sql = <<<SQL
          SELECT entity_id FROM node__website_url
          UNION
          SELECT entity_id FROM node__landing_page
          SQL;
        return $this->db->query($sql)->fetchCol();

      default:
        return NULL;
    }
  }

}
