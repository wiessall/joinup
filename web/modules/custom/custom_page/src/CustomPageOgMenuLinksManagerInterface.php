<?php

declare(strict_types=1);

namespace Drupal\custom_page;

use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeInterface;
use Drupal\og_menu\OgMenuInstanceInterface;

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
   * Adds a OG menu link pointing to a custom page.
   *
   * @param \Drupal\node\NodeInterface $customPage
   *   The custom page entity.
   * @param string $menuType
   *   The type of the OG menu instance. Whether 'navigation' or 'quick_links'.
   * @param array $properties
   *   (optional) The properties of the menu link. Defaults to an empty array.
   *
   * @return $this
   */
  public function addLink(NodeInterface $customPage, string $menuType, array $properties): CustomPageOgMenuLinksManagerInterface;

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

  /**
   * Deletes all the corresponding OG menu links pointing to a custom page.
   *
   * @param \Drupal\node\NodeInterface $customPage
   *   The custom page entity.
   *
   * @return $this
   */
  public function deleteLinks(NodeInterface $customPage): CustomPageOgMenuLinksManagerInterface;

  /**
   * Gets the OG menu instance, given a custom page.
   *
   * @param \Drupal\node\NodeInterface $customPage
   *   The custom page entity.
   * @param string $menuType
   *   The type of the OG menu instance. Whether 'navigation' or 'quick_links'.
   *
   * @return \Drupal\og_menu\OgMenuInstanceInterface|null
   *   The OG menu instance or NULL if none can be determined.
   */
  public function getOgMenuInstanceByCustomPage(NodeInterface $customPage, string $menuType): ?OgMenuInstanceInterface;

}
