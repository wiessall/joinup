<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\OgMembershipInterface;

/**
 * The interface for the JoinupGroupManager service.
 */
interface JoinupGroupManagerInterface {

  /**
   * Retrieves all the collections where a user is the sole owner.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to retrieve memberships for.
   *
   * @return \Drupal\rdf_entity\Entity\Rdf[]
   *   An array of collections.
   */
  public function getGroupsWhereSoleOwner(AccountInterface $user): array;

  /**
   * Returns the memberships of a user for a given bundle.
   *
   * Use this to retrieve for example all the user's collection or solution
   * memberships.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user for which to retrieve the memberships.
   * @param string $entity_type_id
   *   The entity type for which to retrieve the memberships.
   * @param string $bundle_id
   *   The bundle for which to retrieve the memberships.
   * @param array $states
   *   The membership states. Defaults to active memberships.
   *
   * @return \Drupal\og\OgMembershipInterface[]
   *   The memberships.
   */
  public function getUserGroupMembershipsByBundle(AccountInterface $user, string $entity_type_id, string $bundle_id, array $states = [OgMembershipInterface::STATE_ACTIVE]): array;

  /**
   * Retrieves all the user memberships with a certain role and state.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to get the memberships for.
   * @param string $role
   *   The role id.
   * @param array $states
   *   (optional) An array of membership states to retrieve. Defaults to active.
   *
   * @return \Drupal\og\OgMembershipInterface[]
   *   An array of OG memberships that match the criteria.
   */
  public function getUserMembershipsByRole(AccountInterface $user, string $role, array $states = [OgMembershipInterface::STATE_ACTIVE]): array;

  /**
   * Checks whether an account has access to a group.
   *
   * In many places, we're performing a group composite access check, either by
   * a site-wide permission or by an OG permission. This method aim is to
   * provide a centralized, unified way to do this check.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group to be checked.
   * @param string $og_permission
   *   The OG permission to be checked.
   * @param string|null $site_permission
   *   (optional) The site-wide permission. If omitted, only the OG check will
   *   be performed.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   (optional) The account for which to perform the check. If omitted, the
   *   current user account will be checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object, containing also cache metadata.
   */
  public function access(GroupInterface $group, string $og_permission, ?string $site_permission = NULL, ?AccountInterface $account = NULL): AccessResultInterface;

  /**
   * Returns the group from the OG context.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The group from the current context, if any.
   */
  public function getGroupFromContext(): ?GroupInterface;

}
