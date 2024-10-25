<?php

declare(strict_types=1);

namespace Drupal\joinup_menu;

use Drupal\og_menu\OgMenuInstanceInterface;

/**
 * Interface describing helper methods for working with menus.
 */
interface MenuHelperInterface {

  /**
   * Returns the account menu in the format expected by the BCL header template.
   *
   * @return array[]
   *   An array of menu links for the account menu in the BCL header template
   *   format, which is an array of arrays with the following keys:
   *   - link: The URL the link points to.
   *   - label: The link label.
   */
  public function getBclAccountMenu(): array;

  /**
   * Returns the anonymous menu in the format used by the BCL header template.
   *
   * @return array[]
   *   An array of menu links for the anonymous menu in the BCL header template
   *   format, which is an array of arrays with the following keys:
   *   - link: The URL the link points to.
   *   - label: The link label.
   */
  public function getBclAnonymousMenu(): array;

  /**
   * Returns landing page links in the format used by the BCL header template.
   *
   * The landing pages returned are:
   *   - Interoperable Europe collection landing page.
   *   - Portal collection landing page.
   *
   * @return array[]
   *   An array of menu links for the anonymous menu in the BCL header template
   *   format, which is an array of arrays with the following keys:
   *   - link: The URL the link points to.
   *   - label: The link label.
   */
  public function getBclLandingPageLinks(): array;

  /**
   * Loads an OG menu instance for the given group.
   *
   * @param string $menuType
   *   The OG menu bundle.
   * @param string $groupId
   *   The group ID.
   *
   * @return \Drupal\og_menu\OgMenuInstanceInterface|null
   *   The OG menu instance or NULL if none was found.
   */
  public function getOgMenuByGroup(string $menuType, string $groupId): ?OgMenuInstanceInterface;

}
