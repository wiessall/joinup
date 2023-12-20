<?php

declare(strict_types = 1);

namespace Drupal\joinup_menu;

use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Interface describing helper methods for working with menus.
 */
interface MenuHelperInterface {

  /**
   * A list of fields and their corersponding internal identifiers.
   *
   * @var string[]
   */
  const IDENTIFIER_MAPPINGS = [
    'field_ar_access_url' => self::INTERNAL_IDENTIFIER_WEBSITE_LINK,
    'field_is_landing_page' => self::INTERNAL_IDENTIFIER_WEBSITE_LINK,
    'field_ar_contact_information' => self::INTERNAL_IDENTIFIER_CONTACT_US,
    'field_is_contact_information' => self::INTERNAL_IDENTIFIER_CONTACT_US,
    'field_is_documentation' => self::INTERNAL_IDENTIFIER_DOCUMENTATION,
  ];

  const INTERNAL_IDENTIFIER_WEBSITE_LINK = 'website-link';
  const INTERNAL_IDENTIFIER_CONTACT_US = 'contact-us';
  const INTERNAL_IDENTIFIER_DOCUMENTATION = 'documentation';
  const INTERNAL_IDENTIFIER_DISCUSSIONS = 'discussions';
  const INTERNAL_IDENTIFIER_EIF_PERSPECTIVE = 'eif-perspective';
  const INTERNAL_IDENTIFIER_OVERVIEW = 'overview';
  const INTERNAL_IDENTIFIER_RELEASES = 'releases';

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
   *   - Joinup collection landing page.
   *
   * @return array[]
   *   An array of menu links for the anonymous menu in the BCL header template
   *   format, which is an array of arrays with the following keys:
   *   - link: The URL the link points to.
   *   - label: The link label.
   */
  public function getBclLandingPageLinks(): array;

  /**
   * Syncs a menu link items after a group is updated.
   *
   * Important: This method should be called during a group update hook. The
   * reason for this is that the only way to have the menu link identified is
   * by the old URL which is not available after the group is updated. The menu
   * link itself can be renamed so there is no other identifier for this.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group that was updated.
   */
  public function syncQuickLinksMenuItems(GroupInterface $group): void;

  /**
   * Returns the URL for the given field.
   *
   * The field must be part of the
   * \Drupal\joinup_menu\MenuHelperInterface::IDENTIFIER_MAPPINGS array keys.
   *
   * @param string $group
   *   The group entity.
   * @param string $fieldName
   *   The field name.
   *
   * @return \Drupal\Core\Url
   *   The URL.
   */
  public function getQuickLinksUrlForField(GroupInterface $group, string $fieldName): Url;

  /**
   * Enables the 'Contact us' menu link for the given group.
   *
   * @param string $groupId
   *   The group ID.
   */
  public function enableContactQuickLink(string $groupId): void;

}
