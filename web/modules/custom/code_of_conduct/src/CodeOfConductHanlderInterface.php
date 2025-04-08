<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct;

use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Interface for Code of Conduct handler.
 */
interface CodeOfConductHanlderInterface {

  /**
   * Handles the group when updated.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   */
  public function onGroupUpdate(GroupInterface $group): void;

  /**
   * Handles the custom page when updated.
   *
   * @param \Drupal\custom_page\Entity\CustomPageInterface $customPage
   *   The custom page entity.
   */
  public function onCodeOfConductUpdate(CustomPageInterface $customPage): void;

  /**
   * Handles the meta entity when created or updated.
   *
   * @param \Drupal\meta_entity\Entity\MetaEntityInterface $metaEntity
   *   The group settings meta entity.
   * @param bool $insert
   *   Whether the entity was inserted or updated. Defaults to FALSE.
   */
  public function onGroupSettingsInsertOrUpdate(MetaEntityInterface $metaEntity, bool $insert = FALSE): void;

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
