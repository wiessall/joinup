<?php

declare(strict_types = 1);

namespace Drupal\joinup_group;

use Drupal\og_menu\OgMenuInstanceInterface;

/**
 * Provides interface for "joinup_group.group_menu_manager" service.
 */
interface GroupMenuManagerInterface {

  /**
   * Returns the menu instance, given a group ID.
   *
   * @param string $groupId
   *   The ID of the group to which OG menu instance is attached.
   * @param string $ogMenuInstanceId
   *   The ID of the OG menu instance.
   *
   * @return \Drupal\og_menu\OgMenuInstanceInterface|null
   *   The OG menu instance or NULL if none can be determined.
   */
  public function getMenuByGroupId(string $groupId, string $ogMenuInstanceId): ?OgMenuInstanceInterface;

}
