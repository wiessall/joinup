<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct;

use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Interface for handling Code of Conduct membership actions.
 */
interface CocMembershipHandlerInterface {

  /**
   * Revokes the full membership of all users in the group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   * @param bool $skipAccepted
   *   (optional) If TRUE, skip the memberships that have accepted the CoC.
   */
  public function revokeFullMemberships(GroupInterface $group, bool $skipAccepted = FALSE): void;

  /**
   * Restores the full membership of all users in the group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   */
  public function restoreFullMemberships(GroupInterface $group): void;

  /**
   * Returns the name of the role that represents a full member.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return string
   *   The role ID which is in the format of
   *   <entity_type>-<bundle>-<role_name>.
   */
  public function getFullMemberRoleId(GroupInterface $group): string;

}
