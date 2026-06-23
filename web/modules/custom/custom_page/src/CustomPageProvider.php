<?php

declare(strict_types=1);

namespace Drupal\custom_page;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\og\OgGroupAudienceHelperInterface;

/**
 * Provides custom pages to whoever desires them.
 */
readonly class CustomPageProvider implements CustomPageProviderInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MenuLinkManagerInterface $menuLinkManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getCustomPagesByGroup(GroupInterface $group, bool $exclude_disabled = TRUE): array {
    // Get a list of all custom pages belonging to the group.
    $custom_page_ids = $this->getCustomPageIds($group->id());

    if (!empty($custom_page_ids) && $exclude_disabled) {
      // Filter out all pages that are disabled in the group's menu instance.
      $ogmenu_instance = $group->getGroupOgMenuInstance('navigation');

      $custom_page_ids = array_filter($custom_page_ids, function (string $custom_page_id) use ($ogmenu_instance) {
        $menu_links = $this->menuLinkManager->loadLinksByRoute(
          'entity.node.canonical',
          ['node' => $custom_page_id],
          'ogmenu-' . $ogmenu_instance->id()
        );
        $menu_link = reset($menu_links);

        return $menu_link instanceof GroupMenuLink && $menu_link->isEnabled();
      });
    }

    if (empty($custom_page_ids)) {
      return [];
    }

    return $this->entityTypeManager->getStorage('node')->loadMultiple($custom_page_ids);
  }

  /**
   * Returns the custom page IDs of published custom pages in the given group.
   *
   * @param string $group_id
   *   The ID of the group for which to return the published custom page IDs.
   *
   * @return string[]
   *   An array of group IDs.
   */
  protected function getCustomPageIds(string $group_id): array {
    return $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD . '.target_id', $group_id)
      ->condition('type', 'custom_page')
      ->condition('status', 1)
      ->execute();
  }

}
