<?php

declare(strict_types=1);

namespace Drupal\custom_page;

use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Provides custom pages to whoever desires them.
 */
interface CustomPageProviderInterface {

  /**
   * Returns the custom pages that belong to the given group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group for which to return the custom pages.
   * @param bool $exclude_disabled
   *   Whether or not to exclude custom pages that are disabled by the group
   *   facilitators and are not visible in the group menu. Defaults to TRUE.
   *
   * @return \Drupal\custom_page\Entity\CustomPageInterface[]
   *   The custom page entities.
   */
  public function getCustomPagesByGroup(GroupInterface $group, bool $exclude_disabled = TRUE): array;

}
