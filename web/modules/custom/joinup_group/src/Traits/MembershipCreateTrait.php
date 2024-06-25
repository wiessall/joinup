<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Traits;

use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleInterface;
use Drupal\user\UserInterface;

/**
 * Trait for creating Organic Groups membership.
 */
trait MembershipCreateTrait {

  /**
   * Creates and saves a new group membership.
   *
   * @param string $state
   *   The state of the new membership.
   * @param \Drupal\user\UserInterface $user
   *   The user account owning the membership.
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group the membership belongs to.
   *
   * @return \Drupal\og\OgMembershipInterface
   *   The new group membership.
   */
  protected function createSaveNewMembership(string $state, UserInterface $user, GroupInterface $group): OgMembershipInterface {
    $authenticatedRoleId = implode('-', [
      $group->getEntityTypeId(),
      $group->bundle(),
      OgRoleInterface::AUTHENTICATED,
    ]);

    $membership = $this->createMembership(
      $state ?? OgMembershipInterface::STATE_ACTIVE,
      [$this->loadOgRole($authenticatedRoleId)],
      $user,
      $group
    );
    $membership->save();
    return $membership;
  }

  /**
   * Creates a membership for the group and the user.
   *
   * @param string $state
   *   The membership state.
   * @param array $roles
   *   The role ids to pass in the membership.
   * @param \Drupal\user\UserInterface $user
   *   The user account owning the membership.
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group the membership belongs to.
   *
   * @return \Drupal\og\OgMembershipInterface
   *   The created membership.
   */
  protected function createMembership(string $state, array $roles, UserInterface $user, GroupInterface $group): OgMembershipInterface {
    /** @var \Drupal\og\OgMembershipInterface $membership */
    $membership = $this->entityTypeManager->getStorage('og_membership')->create([
      'type' => OgMembershipInterface::TYPE_DEFAULT,
    ]);
    $membership
      ->setOwner($user)
      ->setGroup($group)
      ->setState($state)
      ->setRoles($roles);

    return $membership;
  }

  /**
   * Loads the OG role with the given ID.
   *
   * @param string $roleId
   *   The OG role ID.
   *
   * @return \Drupal\og\OgRoleInterface
   *   The OG role entity.
   */
  protected function loadOgRole(string $roleId): OgRoleInterface {
    $role = $this->entityTypeManager->getStorage('og_role')->load($roleId);
    assert($role instanceof OgRoleInterface);
    return $role;
  }

}
