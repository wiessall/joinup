<?php

declare(strict_types = 1);

namespace Drupal\custom_page;

use Drupal\custom_page\Entity\CustomPage;

/**
 * Provides custom pages to whoever desires them.
 */
interface CustomPageProviderInterface {

  const ERROR_MESSAGE = 'This page acts as landing page for the group %s. In order to delete/unpublish it should be first unset as landing page.';

  /**
   * Returns the custom pages that belong to the given group.
   *
   * @param string $group_id
   *   The entity ID of the group for which to return the custom pages.
   * @param bool $exclude_disabled
   *   Whether or not to exclude custom pages that are disabled by the group
   *   facilitators and are not visible in the group menu. Defaults to TRUE.
   *
   * @return \Drupal\custom_page\Entity\CustomPageInterface[]
   *   The custom page entities.
   */
  public function getCustomPagesByGroupId(string $group_id, bool $exclude_disabled = TRUE): array;

  /**
   * Validate if custom page is set as a landing page.
   *
   * @param \Drupal\custom_page\Entity\CustomPage $custom_page
   *   Custom page entity.
   *
   * @return bool
   *   Return true if
   */
  public function validateLandingPage(CustomPage $custom_page): bool;

}
