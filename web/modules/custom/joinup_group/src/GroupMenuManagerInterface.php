<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\og_menu\OgMenuInstanceInterface;

/**
 * Provides interface for "joinup_group.group_menu_manager" service.
 */
interface GroupMenuManagerInterface {

  /**
   * Returns the menu instance, given a group ID.
   *
   * @param string|int $groupId
   *   The ID of the group to which OG menu instance is attached.
   * @param string $menuType
   *   The type of the OG menu instance.
   *
   * @return \Drupal\og_menu\OgMenuInstanceInterface|null
   *   The OG menu instance or NULL if none can be determined.
   */
  public function getMenuByGroupId(string|int $groupId, string $menuType): ?OgMenuInstanceInterface;

}
