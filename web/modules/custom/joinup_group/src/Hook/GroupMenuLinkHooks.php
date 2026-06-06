<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\collection\Entity\GlossaryTermInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\GroupMenuLinkInterface;
use Drupal\joinup_group\GroupMenuLinkHelperTrait;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\og\OgGroupAudienceHelperInterface;

/**
 * Hook implementations related to group menu links.
 */
class GroupMenuLinkHooks {

  use GroupMenuLinkHelperTrait;

  public function __construct(
    readonly protected MenuLinkManagerInterface $menuLinkManager,
    readonly protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds an overview menu link to the new group.
   */
  #[Hook('node_insert')]
  public function addGroupOverviewMenuLink(NodeInterface $group): void {
    if (!$group instanceof GroupInterface) {
      return;
    }

    // When a group is created, create two new navigation menus and associate
    // them with the new collection or solution. These menus can be used to link
    // to custom pages, overviews, etc., but come with a default set of links
    // that can be used as a starting point.
    $storage = $this->entityTypeManager->getStorage('ogmenu_instance');
    foreach (['navigation', 'quick_links'] as $type) {
      $storage->create([
        'type' => $type,
        OgGroupAudienceHelperInterface::DEFAULT_FIELD => $group->id(),
      ])->save();
    }

    $this->addGroupLink(
      linkType: 'overview',
      group: $group,
      ogMenuType: 'navigation',
      title: 'Overview',
      routeName: 'entity.node.canonical',
      weight: -10,
    );
  }

  /**
   * Updates the overview menu link title when the label is changed.
   */
  #[Hook('meta_entity_insert')]
  #[Hook('meta_entity_update')]
  public function updateGroupOverviewMenuLinkTitle(MetaEntityInterface $settings): void {
    if (!$this->isGroupSettings($settings)) {
      return;
    }

    $title = $settings->get('overview_label')->value ?? 'Overview';
    $originalTitle = $settings->getOriginal()?->get('overview_label')->value;
    if ($title !== $originalTitle) {
      // The title has been changed, update the menu link.
      $this->updateOverviewTitle($settings, $title);
    }
  }

  /**
   * Resets the overview menu link title on group settings deletion.
   */
  #[Hook('meta_entity_delete')]
  public function resetGroupOverviewMenuLinkTitle(MetaEntityInterface $settings): void {
    if ($this->isGroupSettings($settings)) {
      // The group settings entity is gone, reset the title to default.
      $this->updateOverviewTitle($settings, 'Overview');
    }
  }

  /**
   * Checks if the meta-entity is a collection or solution settings entity.
   *
   * @param \Drupal\meta_entity\Entity\MetaEntityInterface $settings
   *   The meta-entity.
   *
   * @return bool
   *   TRUE if the meta-entity is a collection or solution settings entity.
   */
  protected function isGroupSettings(MetaEntityInterface $settings): bool {
    return in_array($settings->bundle(), ['collection_settings', 'solution_settings'], TRUE);
  }

  /**
   * Updates the overview group menu link title.
   *
   * @param \Drupal\meta_entity\Entity\MetaEntityInterface $settings
   *   The collection settings entity.
   * @param string $title
   *   The new title to be set.
   */
  protected function updateOverviewTitle(MetaEntityInterface $settings, string $title): void {
    assert($this->isGroupSettings($settings));

    if (!$groupId = $settings->get('target')->first()->getValue()['target_id'] ?? NULL) {
      return;
    }

    $pluginId = GroupMenuLink::buildPluginId('overview', $groupId, 'navigation');
    if ($definition = $this->menuLinkManager->getDefinition($pluginId, FALSE)) {
      $definition['title'] = $title;
      $this->menuLinkManager->updateDefinition($pluginId, $definition, FALSE);
    }
  }

  /**
   * Adds glossary menu links to groups.
   */
  #[Hook('node_insert')]
  public function addGlossaryGroupMenuLink(NodeInterface $glossary): void {
    if ($glossary instanceof GlossaryTermInterface && $glossary->hasGroup()) {
      $this->addGroupLink(
        linkType: 'glossary',
        group: $glossary->getGroup(),
        ogMenuType: 'navigation',
        title: 'Glossary',
        routeName: 'entity.node.glossary',
        weight: -7,
      );

      foreach ($glossary->getGroup()->getSolutions() as $solution) {
        $this->addGroupLink(
          linkType: 'glossary',
          group: $solution,
          ogMenuType: 'navigation',
          title: 'Glossary',
          routeName: 'entity.node.glossary',
          enabled: FALSE,
          weight: -7,
          overrides: [
            // Point this link to the solution collection glossary page.
            'route_parameters' => ['node' => $glossary->getGroupId()],
          ]);
      }
    }
  }

  /**
   * Deletes glossary menu links from the groups.
   */
  #[Hook('node_delete')]
  public function deleteGlossaryGroupMenuLink(NodeInterface $glossary): void {
    if (!$glossary instanceof GlossaryTermInterface || !$glossary->hasGroup()) {
      return;
    }

    if ($this->groupHasContent($glossary)) {
      // Keep the link as there are still glossary terms in this collection.
      return;
    }

    $this->removeGroupLink('glossary', $glossary, ['navigation']);
    foreach ($glossary->getGroup()->getSolutions() as $solution) {
      $this->removeGroupLink('glossary', $solution, ['navigation']);
    }
  }

  /**
   * Updates the solution glossary links when the solution in moved.
   *
   * Solution glossary links are pointing to the collection glossary page. If
   * the solution is moved to another collection, we need to update the link but
   * only if the target collection has glossary terms.
   */
  #[Hook('node_update')]
  public function updateGlossaryGroupMenuLink(NodeInterface $solution): void {
    if (!$solution instanceof SolutionInterface) {
      return;
    }

    $originalCollectionId = $solution->getOriginal()->getGroupId();
    $collectionId = $solution->getGroupId();

    if ($collectionId == $originalCollectionId) {
      // Parent collection hasn't changed, nothing to do.
      return;
    }

    $collectionPluginId = GroupMenuLink::buildPluginId('glossary', $collectionId, 'navigation');
    $solutionPluginId = GroupMenuLink::buildPluginId('glossary', $solution->id(), 'navigation');

    if (!$this->menuLinkManager->hasDefinition($collectionPluginId)) {
      // The destination collection doesn't have a glossary link, remove it also
      // from the solution.
      $this->menuLinkManager->removeDefinition($solutionPluginId);
      return;
    }

    if ($solutionDefinition = $this->menuLinkManager->getDefinition($solutionPluginId, FALSE)) {
      // Update the link to point to the destination collection glossary page.
      $solutionDefinition['route_parameters'] = ['node' => $collectionId];
      $this->menuLinkManager->updateDefinition($solutionPluginId, $solutionDefinition, FALSE);
    }
  }

  /**
   * Adds or removes the website_link group menu link.
   */
  #[Hook('node_insert')]
  #[Hook('node_update')]
  public function updateGroupWebsiteLinkLink(NodeInterface $group): void {
    if (!$group instanceof GroupInterface) {
      return;
    }

    $uri = $group->get($group->getWebsiteLinkFieldName())->uri;
    $originalUri = $group->getOriginal()?->get($group->getWebsiteLinkFieldName())->uri;

    if ($uri && !$originalUri) {
      $this->addGroupLink(
        linkType: 'website_link',
        group: $group,
        ogMenuType: 'quick_links',
        title: 'Website link',
        routeName: 'joinup_menu.website_link',
      );
    }
    elseif (!$uri && $originalUri) {
      $pluginId = GroupMenuLink::buildPluginId('website_link', $group->id(), 'quick_links');
      $this->menuLinkManager->removeDefinition($pluginId, FALSE);
    }
  }

  /**
   * Updates the group menu link plugin definition on entity update.
   *
   * This hook implementation handles the case where the group menu link is
   * derived over content. A notable case is the group menu links created for
   * custom pages.
   */
  #[Hook('node_update')]
  #[Hook('node_insert')]
  public function updateNodeGroupMenuLinkPluginDefinition(NodeInterface $node): void {
    if (!$this->isGroupMenuLinkNode($node)) {
      return;
    }
    assert($node instanceof GroupMenuLinkInterface);

    $menuSettings = $node->get('menu_settings')->value;
    foreach ($node->getGroup()->getGroupOgMenuInstances() as $ogMenuInstance) {
      $ogMenuType = $ogMenuInstance->bundle();

      $definition = [
        'class' => GroupMenuLink::class,
        'provider' => 'joinup_group',
        'menu_name' => "ogmenu-{$ogMenuInstance->id()}",
        'title' => $node->label(),
        'route_name' => 'entity.node.canonical',
        'route_parameters' => ['node' => $node->id()],
        'parent' => $menuSettings[$ogMenuType]['parent'],
        'weight' => $menuSettings[$ogMenuType]['weight'],
        'enabled' => $menuSettings[$ogMenuType]['enabled'],
      ];

      $pluginId = GroupMenuLink::buildPluginId('node', $node->id(), $ogMenuType);
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
  public function deleteNodeGroupMenuLinkPluginDefinition(NodeInterface $node): void {
    if (!$this->isGroupMenuLinkNode($node)) {
      return;
    }
    assert($node instanceof GroupMenuLinkInterface);

    foreach ($node->getGroup()->getGroupOgMenuInstances() as $ogMenuInstance) {
      $ogMenuType = $ogMenuInstance->bundle();
      $pluginId = GroupMenuLink::buildPluginId('node', $node->id(), $ogMenuType);
      if ($definition = $this->menuLinkManager->getDefinition($pluginId, FALSE)) {
        $parent = $definition['parent'] ?? '';
        $nids = [];
        foreach ($this->menuLinkManager->getChildIds($pluginId) as $childPluginId) {
          if ($nid = GroupMenuLink::extractNodeId($childPluginId)) {
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

      $this->menuLinkManager->removeDefinition($pluginId);
    }
  }

  /**
   * Checks if the given entity is a group menu link node and exposes a group.
   *
   * A typical case of a group menu link node is the custom page when it is part
   * of a collection or solution.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return bool
   *   TRUE if the node is a group menu link node, exposes a group and is the
   *   default revision.
   */
  protected function isGroupMenuLinkNode(NodeInterface $node): bool {
    if (
      // Only allow group menu link entities.
      !$node instanceof GroupMenuLinkInterface
      // Don't update the menu tree if a pending revision was saved.
      || !$node->isDefaultRevision()
    ) {
      return FALSE;
    }

    return $node->hasGroup();
  }

}
