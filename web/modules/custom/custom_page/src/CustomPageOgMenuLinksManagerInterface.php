<?php

declare(strict_types=1);

namespace Drupal\custom_page;

use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeInterface;

/**
 * Provides an interface for the custom page OG menu links manager.
 */
interface CustomPageOgMenuLinksManagerInterface {

  /**
   * Returns a list of child custom pages linked through OG menu links.
   *
   * @param \Drupal\node\NodeInterface $customPage
   *   The parent custom page node.
   * @param string $menuType
   *   The type of the OG menu instance. Whether 'navigation' or 'quick_links'.
   *
   * @return \Drupal\node\NodeInterface[]
   *   A list of child nodes.
   */
  public function getChildren(NodeInterface $customPage, string $menuType): array;

  /**
   * Move the OG menu links of a custom page to a new group OG menu instance.
   *
   * @param \Drupal\node\NodeInterface $customPage
   *   The custom page entity.
   * @param string $groupId
   *   The ID of the group where the custom page is moved.
   *
   * @return $this
   */
  public function moveLinks(NodeInterface $customPage, string $groupId): CustomPageOgMenuLinksManagerInterface;

  /**
   * Updates index of group, when link is enabled/disabled.
   *
   * Whenever a custom page that is linked in the navigation menu of
   *  a collection or solution becomes enabled or disabled
   *  we need to reindex the group, since the content of the custom menu
   *  should now be included or excluded.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $menuLink
   *   The menu link content.
   */
  public function updateLink(MenuLinkContentInterface $menuLink): void;

}
