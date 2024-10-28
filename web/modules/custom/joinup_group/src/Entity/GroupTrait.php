<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_core\Entity\OutdatedContentTrait;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\og\Entity\OgRole;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Reusable methods for group bundles.
 */
trait GroupTrait {

  use OutdatedContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getMembership(?int $uid = NULL, array $states = [OgMembershipInterface::STATE_ACTIVE]): ?OgMembershipInterface {
    assert(is_subclass_of($this, GroupInterface::class), __TRAIT__ . ' is intended to be used in bundle classes for group entities.');

    // Default to the current user.
    $uid = $uid ?? \Drupal::currentUser()->id();

    return $this->getMembershipManager()->getMembership($this, $uid, $states);
  }

  /**
   * Creates a membership for the given user in the current group.
   *
   * @param int|null $uid
   *   The ID of the user that will become a member. Defaults to the current
   *   user.
   * @param string|null $role
   *   The role to assign to the user. Can be either 'member', 'author', or
   *   'facilitator'. Owners cannot be added using this method since every group
   *   has a single owner which is assigned when the group is created or
   *   transferred. Defaults to 'member'.
   * @param string|null $state
   *   The state of the membership. It may be of the following constants:
   *   - OgMembershipInterface::STATE_ACTIVE
   *   - OgMembershipInterface::STATE_PENDING
   *   - OgMembershipInterface::STATE_BLOCKED.
   *   Defaults to the most appropriate state: 'active' for solutions and open
   *   collections, and 'pending' for closed collections.
   *
   * @return \Drupal\og\OgMembershipInterface
   *   The membership that was created.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when an error occurs during the saving of the membership.
   */
  public function createMembership(?int $uid = NULL, ?string $role = 'member', ?string $state = NULL): OgMembershipInterface {
    // Default to the current user.
    $uid = $uid ?? \Drupal::currentUser()->id();

    // Default to an active membership.
    $state = $state ?? OgMembershipInterface::STATE_ACTIVE;

    // Validate the user.
    $user = User::load($uid);
    if (!$user instanceof UserInterface) {
      throw new \InvalidArgumentException('Cannot create membership for non-existing user.');
    }
    if ($user->isAnonymous()) {
      throw new \InvalidArgumentException('Cannot create membership for an anonymous user.');
    }

    // Validate the role. We cannot create additional memberships for owners /
    // administrators since in Interoperable Europe Portal every group has a single owner.
    if (!in_array($role, ['member', 'author', 'facilitator'])) {
      throw new \InvalidArgumentException("Cannot create membership with role '$role'.");
    }

    $og_role = OgRole::loadByGroupAndName($this, $role);
    if (!$og_role instanceof OgRoleInterface) {
      throw new \InvalidArgumentException("Can not create membership because the '$role' could not be loaded.");
    }

    $membership = $this->getMembershipManager()->createMembership($this, $user);
    $membership->setState($state);
    $membership->setRoles([$og_role]);
    $membership->save();

    return $membership;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupAccess(string $permission, ?AccountInterface $user = NULL): AccessResultInterface {
    assert(is_subclass_of($this, GroupInterface::class), __TRAIT__ . ' is intended to be used in bundle classes for group entities.');

    /** @var \Drupal\og\OgAccessInterface $og_access */
    $og_access = \Drupal::service('og.access');

    return $og_access->userAccess($this, $permission, $user);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOwners(): array {
    return array_map(function (OgMembershipInterface $membership): UserInterface {
      return $membership->getOwner();
    }, $this->getMembershipManager()->getGroupMembershipsByRoleNames($this, ['administrator']));
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOwnerIds(): array {
    return array_map(function (OgMembershipInterface $membership): int {
      return (int) $membership->getOwnerId();
    }, $this->getMembershipManager()->getGroupMembershipsByRoleNames($this, ['administrator']));
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupOwner(int $uid): bool {
    return in_array($uid, $this->getGroupOwnerIds());
  }

  /**
   * {@inheritdoc}
   */
  public function isSoleGroupOwner(int $uid): bool {
    $owner_ids = $this->getGroupOwnerIds();
    return count($owner_ids) == 1 && in_array($uid, $owner_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function hasGroupPermission(int|string $uid, string $permission): bool {
    $membership_manager = $this->getMembershipManager();
    if ($membership = $membership_manager->getMembership($this, $uid)) {
      return $membership->hasPermission($permission);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isModerated(): bool {
    return (int) $this->getMainPropertyValue($this->getGroupModerationFieldName()) === GroupInterface::PRE_MODERATION;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentCreators(): ?string {
    $contentCreators = $this->getMainPropertyValue($this->getContentCreationFieldName());

    if (is_null($contentCreators)) {
      $loggerFactory = \Drupal::service('logger.factory');
      $loggerFactory->get('joinup_group')->warning('Invalid content creators');
    }

    return $contentCreators;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupContentIds(): array {
    $group_content = $this->doGetGroupContentIds();
    // Ensure that the results are sorted.
    ksort($group_content);
    array_walk($group_content, function (array &$ids_by_bundle): void {
      ksort($ids_by_bundle);
      array_walk($ids_by_bundle, function (array &$ids): void {
        // Sorting using array_walk($ids_by_bundle, 'sort') short form, just
        // doesn't work because array_walk() passes the array key as a second
        // callback parameter. So sort() will receive the array key as second
        // parameter, but the function expects a total different value there.
        sort($ids);
      });
    });
    return $group_content;
  }

  /**
   * {@inheritdoc}
   */
  public function getMemberCount(array $states = [OgMembershipInterface::STATE_ACTIVE]): int {
    return \Drupal::entityQuery('og_membership')
      ->accessCheck(FALSE)
      ->condition('entity_type', 'rdf_entity')
      ->condition('entity_id', $this->id())
      ->condition('state', $states, 'IN')
      ->count()
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting(string $field_name): FieldItemListInterface {
    $meta_entity_bundle = "{$this->bundle()}_settings";
    $meta_entity = MetaEntity::loadOrCreate($meta_entity_bundle, $this);
    if (!$meta_entity->hasField($field_name)) {
      throw new \InvalidArgumentException("Field $field_name doesn't exist");
    }
    return $meta_entity->get($field_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers(int $offset = 0, ?int $limit = NULL, array $roles = [OgRoleInterface::AUTHENTICATED], array $states = [OgMembershipInterface::STATE_ACTIVE]): array {
    $og_membership_ids = $this->getMembershipManager()->getGroupMembershipIdsByRoleNames($this, $roles, $states);
    if (empty($og_membership_ids)) {
      return [];
    }
    sort($og_membership_ids);
    $og_membership_ids = $limit === NULL ? array_splice($og_membership_ids, $offset) : array_splice($og_membership_ids, $offset, $limit);

    $entity_type_manager = \Drupal::entityTypeManager();
    $uids = array_values(array_map(function (OgMembershipInterface $membership): int {
      return (int) $membership->getOwnerId();
    }, $entity_type_manager->getStorage('og_membership')->loadMultiple($og_membership_ids)));
    sort($uids);
    return $entity_type_manager->getStorage('user')->loadMultiple($uids);
  }

  /**
   * Processes and returns a list of group content entities.
   *
   * @return array
   *   An associative array keyed by the group content entity type ID. Each
   *   value is an associative array keyed by entity bundle and having the node
   *   IDs as values.
   */
  abstract protected function doGetGroupContentIds(): array;

  /**
   * Returns a list of group content node IDs, grouped by node type.
   *
   * @return int[][]
   *   An associative array keyed by node type and having node IDs as values.
   */
  protected function getNodeGroupContent(): array {
    $storage = $this->entityTypeManager()->getStorage('node');

    $nids = $storage->getQuery()->accessCheck(FALSE)
      ->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $this->id())
      ->execute();

    if (!$nids) {
      return [];
    }

    $nids_per_bundle = [];
    foreach ($storage->loadMultiple($nids) as $nid => $node) {
      $nids_per_bundle[$node->bundle()][] = $nid;
    }

    return $nids_per_bundle;
  }

  /**
   * Returns the membership manager.
   *
   * @return \Drupal\og\MembershipManagerInterface
   *   The membership manager.
   */
  protected function getMembershipManager(): MembershipManagerInterface {
    return \Drupal::service('og.membership_manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveMemberCount(): int {
    // We're doing a direct database query rather than using methods in OG's
    // MembershipManager service since we just need a count, and we need to
    // filter out blocked user accounts.
    $query = \Drupal::database()->select('og_membership', 'om');
    $query->innerJoin('users_field_data', 'ufd', '[om].[uid] = [ufd].[uid]');
    $query->condition('ufd.status', 1)
      ->condition('entity_type', 'rdf_entity')
      ->condition('entity_id', $this->id())
      ->condition('state', OgMembershipInterface::STATE_ACTIVE);
    return (int) $query->countQuery()->execute()->fetchField();
  }

}
