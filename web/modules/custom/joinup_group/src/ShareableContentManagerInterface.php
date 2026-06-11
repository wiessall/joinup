<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;

/**
 * The interface for the ShareableContentManager service.
 */
interface ShareableContentManagerInterface {

  /**
   * Retrieves a list of groups where the entity can be shared on.
   *
   * @param \Drupal\joinup_group\Entity\ShareableContentInterface $entity
   *   Entity to share.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   A list of groups where the current entity can be shared on.
   */
  public function getShareableGroups(ShareableContentInterface $entity): array;

  /**
   * Retrieves a list of groups of a user filtered by specific a permission.
   *
   * @param string $permission
   *   A permission to filter the roles of the user by.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   An array of groups.
   */
  public function getUserGroupsByPermission(string $permission): array;

  /**
   * Returns the name of the permission needed for the given action.
   *
   * @param string $action
   *   The action name. Can be either 'share' or 'unshare'.
   * @param \Drupal\joinup_group\Entity\ShareableContentInterface $entity
   *   Entity to share.
   *
   * @return string
   *   The permission name.
   */
  public function getPermissionForAction(string $action, ShareableContentInterface $entity): string;

  /**
   * Returns a list of groups that the entity cannot be shared on.
   *
   * @param \Drupal\joinup_group\Entity\ShareableContentInterface $entity
   *   Entity to share.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The affiliated or parent group, if one exists.
   */
  public function getExcludedParent(ShareableContentInterface $entity): ?GroupInterface;

  /**
   * Gets a list of group ids where the current entity is already shared.
   *
   * @param \Drupal\joinup_group\Entity\ShareableContentInterface $entity
   *   Entity to share.
   *
   * @return array
   *   A list of group ids where the current entity is already shared on.
   */
  public function getAlreadySharedGroupIds(ShareableContentInterface $entity): array;

}
