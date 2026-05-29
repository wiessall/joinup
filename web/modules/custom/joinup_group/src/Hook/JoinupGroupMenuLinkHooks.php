<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\joinup_group\Entity\GroupMenuLinkInterface;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLinkDeriver;
use Drupal\node\NodeInterface;
use Drupal\og_menu\OgMenuInstanceInterface;

/**
 * Hook implementations related to group menu links.
 *
 * @todo What if an OG menu instance bundle is deleted?
 */
readonly class JoinupGroupMenuLinkHooks {

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Ensures sane defaults for each OG menu instance setting.
   */
  #[Hook('node_presave')]
  public function setEntityGroupMenuLinksDefaultSettings(NodeInterface $node): void {
    if (!$this->isGroupMenuLinkEntity($node)) {
      return;
    }
    assert($node instanceof GroupMenuLinkInterface);

    $menuSettings = $node->get('menu_settings')->value ?? [];
    foreach ($node->getGroup()->getGroupOgMenuInstances() as $ogMenuInstance) {
      $ogMenuType = $ogMenuInstance->bundle();
      // Ensure sane defaults.
      $menuSettings[$ogMenuType] ??= [];
      $menuSettings[$ogMenuType] += $this->getMenuSettingsDefaults($ogMenuInstance);
    }
    $node->set('menu_settings', ['value' => $menuSettings]);
  }

  /**
   * Updates the group menu link plugin definition on entity update.
   */
  #[Hook('node_update')]
  #[Hook('node_insert')]
  public function updateEntityGroupMenuLinkPluginDefinition(NodeInterface $node): void {
    if (!$this->isGroupMenuLinkEntity($node)) {
      return;
    }
    assert($node instanceof GroupMenuLinkInterface);

    $menuSettings = $node->get('menu_settings')->value;
    foreach ($node->getGroup()->getGroupOgMenuInstances() as $ogMenuInstance) {
      $ogMenuType = $ogMenuInstance->bundle();

      $definition = $this->buildPluginDefinition($node->id(), $ogMenuInstance, $node->label(), [
        'route_name' => 'entity.node.canonical',
        'route_parameters' => ['node' => $node->id()],
        'parent' => $menuSettings[$ogMenuType]['parent'],
        'weight' => $menuSettings[$ogMenuType]['weight'],
        'enabled' => $menuSettings[$ogMenuType]['enabled'],
      ]);
      $pluginId = $definition['id'];

      if ($this->menuLinkManager->hasDefinition($pluginId)) {
        if (!$node->isInsidePlugin()) {
          // The entity is saved via a plugin instance, don't call the menu
          // tree manager to update the definition a second time.
          $this->menuLinkManager->updateDefinition($pluginId, $definition, FALSE);
        }
      }
      else {
        $this->menuLinkManager->addDefinition($pluginId, $definition);
      }
    }
  }

  /**
   * Removes the group menu link plugin definition on entity delete.
   *
   * Orphan links are moved to the parent of the deleted menu item.
   */
  #[Hook('node_predelete')]
  public function deleteEntityGroupMenuLinkPluginDefinition(NodeInterface $node): void {
    if (!$this->isGroupMenuLinkEntity($node)) {
      return;
    }
    assert($node instanceof GroupMenuLinkInterface);

    foreach ($node->getGroup()->getGroupOgMenuInstances() as $ogMenuInstance) {
      $ogMenuType = $ogMenuInstance->bundle();
      $pluginId = GroupMenuLink::buildPluginId($node->id(), $ogMenuType);
      if ($definition = $this->menuLinkManager->getDefinition($pluginId, FALSE)) {
        $parent = $definition['parent'] ?? '';
        $nids = [];
        foreach ($this->menuLinkManager->getChildIds($pluginId) as $childPluginId) {
          if ($nid = GroupMenuLink::extractId($childPluginId)) {
            $nids[] = $nid;
          }
        }

        if ($nids) {
          // Move the child links to the parent of the deleted menu item.
          foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $childPage) {
            $childMenuSettings = $childPage->get('menu_settings')->value;
            $childMenuSettings[$ogMenuType]['parent'] = $parent;
            $childPage->set('menu_settings', ['value' => $childMenuSettings])->save();
          }
        }
      }

      $this->menuLinkManager->removeDefinition($pluginId, FALSE);
    }
  }

  /**
   * Checks if the given entity is a group menu link entity and exposes a group.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The node entity.
   *
   * @return bool
   *   TRUE if the entity is a group menu link entity and exposes a group.
   */
  protected function isGroupMenuLinkEntity(NodeInterface $entity): bool {
    if (
      // Only allow group menu link entities.
      !$entity instanceof GroupMenuLinkInterface
      // Don't update the menu tree if a pending revision was saved.
      || !$entity->isDefaultRevision()
    ) {
      return FALSE;
    }

    return $entity->hasGroup();
  }

  /**
   * Builds the plugin definition for a group menu link entity.
   *
   * @param int|string $id
   *   The identifier used when building the plugin ID.
   * @param \Drupal\og_menu\OgMenuInstanceInterface $ogMenuInstance
   *   The OG menu instance entity.
   * @param string $title
   *   The title of the menu item.
   * @param array $customProperties
   *   Other custom properties to be added to the plugin definition.
   *
   * @return array{id: string, class: string, deriver: string, menu_name: string, title: string, route_name: string, route_parameters: array{node: int|string}, parent: string, weight: int, enabled: bool}
   *   The plugin definition.
   */
  public function buildPluginDefinition(int|string $id, OgMenuInstanceInterface $ogMenuInstance, string $title, array $customProperties): array {
    return $customProperties + [
      'id' => GroupMenuLink::buildPluginId($id, $ogMenuInstance->bundle()),
      'class' => GroupMenuLink::class,
      'deriver' => GroupMenuLinkDeriver::class,
      'provider' => 'joinup_group',
      'menu_name' => "ogmenu-{$ogMenuInstance->id()}",
      'title' => $title,
    ];
  }

  /**
   * Returns the default menu settings for a given OG menu instance.
   *
   * @param \Drupal\og_menu\OgMenuInstanceInterface $ogMenuInstance
   *   The OG menu instance.
   *
   * @return array{parent: string, weight: int, enabled: bool}
   *   The default menu settings for the given OG menu instance.
   */
  protected function getMenuSettingsDefaults(OgMenuInstanceInterface $ogMenuInstance): array {
    return [
      'parent' => '',
      'weight' => 0,
      'enabled' => $ogMenuInstance->bundle() === 'navigation',
    ];
  }

}
