<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;

/**
 * Reusable code by hook implementations adding group menu links.
 */
trait GroupMenuLinkHelperTrait {

  /**
   * Adds a group menu link definition.
   *
   * @param string $linkType
   *   The link type.
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   * @param string $ogMenuType
   *   The OG menu instance type.
   * @param string $title
   *   The link title.
   * @param string $routeName
   *   The route name.
   * @param string $parent
   *   The parent menu link.
   * @param bool $enabled
   *   Whether the link is enabled.
   * @param int $weight
   *   The weight of the link.
   * @param array $overrides
   *   If passed, these values will override the defaults.
   */
  protected function addLink(
    string $linkType,
    GroupInterface $group,
    string $ogMenuType,
    string $title,
    string $routeName,
    string $parent = '',
    bool $enabled = TRUE,
    int $weight = 0,
    array $overrides = [],
  ): void {
    $pluginId = GroupMenuLink::buildPluginId($linkType, $group->id(), $ogMenuType);
    if (!$this->menuLinkManager->hasDefinition($pluginId)) {
      $ogMenuInstance = $group->getGroupOgMenuInstance($ogMenuType);
      $this->menuLinkManager->addDefinition($pluginId, $overrides + [
        'class' => GroupMenuLink::class,
        'provider' => 'joinup_group',
        'menu_name' => "ogmenu-{$ogMenuInstance->id()}",
        'title' => $title,
        'route_name' => $routeName,
        'route_parameters' => ['node' => $group->id()],
        'parent' => $parent,
        'enabled' => $enabled,
        'weight' => $weight,
      ]);
    }
  }

  /**
   * Checks if a group still has a certain type of group content.
   *
   * @param \Drupal\joinup_group\Entity\GroupContentInterface $content
   *   The group content entity to check for.
   *
   * @return bool
   *   TRUE if the group still has content of the given type.
   */
  protected function groupHasContent(GroupContentInterface $content): bool {
    return (bool) $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $content->bundle())
      ->condition('og_audience', $content->getGroupId())
      ->count()
      ->execute();
  }

}
