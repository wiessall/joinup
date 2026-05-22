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
 * @todo what if og menu deletes?
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
  public function ensureMenuSettingsDefaults(NodeInterface $entity): void {
    if (!$this->isGroupMenuLinkEntity($entity)) {
      return;
    }
    assert($entity instanceof GroupMenuLinkInterface);

    $menuSettings = $entity->get('menu_settings')->value ?? [];

    foreach ($entity->getGroup()->getGroupOgMenuInstances() as $id => $ogmenuInstance) {
      $menuSettings[$id] ??= [];
      // Ensure sane defaults.
      $menuSettings[$id] += $this->getMenuSettingsDefaults($ogmenuInstance);
    }

    $entity->set('menu_settings', ['value' => $menuSettings]);
  }

  /**
   * Updates the group menu link plugin definition on entity update.
   */
  #[Hook('node_update')]
  #[Hook('node_insert')]
  public function updateGroupMenuLinkPluginDefinition(NodeInterface $entity): void {
    if (!$this->isGroupMenuLinkEntity($entity)) {
      return;
    }
    assert($entity instanceof GroupMenuLinkInterface);

    $menuSettings = $entity->get('menu_settings')->value;
    $originalMenuSettings = $entity->getOriginal()?->get('menu_settings')->value ?? [];

    $ids = [];
    foreach ($menuSettings as $id => $settings) {
      foreach ($settings as $key => $value) {
        $originalValue = $originalMenuSettings[$id][$key] ?? NULL;
        if ($value !== $originalValue) {
          $ids[] = $id;
          // Jump to next OG menu instance.
          break;
        }
      }
    }

    if (!$ids) {
      // No changes detected to menu settings.
      return;
    }

    $instances = $this->entityTypeManager->getStorage('ogmenu_instance')->loadMultiple($ids);
    foreach ($instances as $id => $instance) {
      $pluginId = GroupMenuLink::id("$id-{$entity->id()}");
      $definition = $this->buildPluginDefinition($entity, $id, $instance->bundle());
      if ($this->menuLinkManager->getDefinition($pluginId, FALSE)) {
        if (!$entity->isInsidePlugin()) {
          // When the entity is saved via a plugin instance, we should not call
          // the menu tree manager to update the definition a second time.
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
  public function deleteGroupMenuLinkPluginDefinition(NodeInterface $entity): void {
    if (!$this->isGroupMenuLinkEntity($entity)) {
      return;
    }

    foreach ($entity->get('menu_settings')->value as $id => $settings) {
      $pluginId = GroupMenuLink::id("$id-{$entity->id()}");

      if ($definition = $this->menuLinkManager->getDefinition($pluginId, FALSE)) {
        $parent = $definition['parent'] ?? '';
        $nids = [];
        foreach ($this->menuLinkManager->getChildIds($pluginId) as $childPluginId) {
          if (preg_match('/^' . GroupMenuLink::BASE_ID . ':\d+-(\d+)$/', $childPluginId, $matches)) {
            $nids[] = $matches[1];
          }
        }

        if ($nids) {
          // Move the child links to the parent of the deleted menu item.
          foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($nids) as $childPage) {
            $childMenuSettings = $childPage->get('menu_settings')->value;
            $childMenuSettings[$id]['parent'] = $parent;
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
      // Only check for group menu link entities.
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
   * @param \Drupal\joinup_group\Entity\GroupMenuLinkInterface $entity
   *   The entity.
   * @param int|string $ogmenuInstanceId
   *   The OG menu instance ID.
   * @param string $ogmenuType
   *   The OG menu instance bundle.
   *
   * @return array{id: string, class: string, deriver: string, menu_name: string, title: string, route_name: string, route_parameters: array{node: int|string}, parent: string, weight: int, enabled: bool}
   *   The plugin definition.
   */
  public function buildPluginDefinition(GroupMenuLinkInterface $entity, int|string $ogmenuInstanceId, string $ogmenuType): array {
    $menuSettings = $entity->get('menu_settings')->value;
    return [
      'id' => GroupMenuLink::id("$ogmenuInstanceId-{$entity->id()}"),
      'class' => GroupMenuLink::class,
      'deriver' => GroupMenuLinkDeriver::class,
      'provider' => 'joinup_group',
      'menu_name' => "ogmenu-$ogmenuInstanceId",
      'title' => $entity->label(),
      'route_name' => 'entity.node.canonical',
      'route_parameters' => ['node' => $entity->id()],
      'parent' => $menuSettings[$ogmenuInstanceId]['parent'] ?? '',
      'weight' => $menuSettings[$ogmenuInstanceId]['weight'] ?? 0,
      'enabled' => $menuSettings[$ogmenuInstanceId]['enabled'] ?? $ogmenuType === 'navigation',
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
