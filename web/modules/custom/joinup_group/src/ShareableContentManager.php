<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Helper methods related to the shareable content.
 */
readonly class ShareableContentManager implements ShareableContentManagerInterface {

  public function __construct(
    #[Autowire(service: 'og.membership_manager')]
    protected readonly MembershipManagerInterface $membershipManager,
    #[Autowire(service: 'og.role_manager')]
    protected readonly OgRoleManagerInterface $roleManager,
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getShareableGroups(ShareableContentInterface $entity): array {
    // Being part also for the access check, do not allow the user to access
    // this page for entities without a field to store groups it is shared in.
    $user_groups = $this->getUserGroupsByPermission($this->getPermissionForAction('share', $entity));
    // Always show collections first.
    uasort($user_groups, function (GroupInterface $group1, GroupInterface $group2): int {
      return $group1->bundle() <=> $group2->bundle();
    });
    if ($parent = $this->getExcludedParent($entity)) {
      unset($user_groups[$parent->id()]);
    }

    return array_diff_key($user_groups, array_flip($this->getAlreadySharedGroupIds($entity)));
  }

  /**
   * {@inheritdoc}
   */
  public function getUserGroupsByPermission(string $permission): array {
    $roles = $this->roleManager->getRolesByPermissions([$permission]);
    if (empty($roles)) {
      return [];
    }

    $groups = $this->membershipManager->getUserGroupsByRoleIds($this->currentUser->id(), array_keys($roles), [OgMembershipInterface::STATE_ACTIVE], FALSE);
    return empty($groups) ? [] : $groups['node'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissionForAction(string $action, ShareableContentInterface $entity): string {
    if (!in_array($action, ['share', 'unshare'])) {
      throw new \InvalidArgumentException('Only "share" and "unshare" are allowed as an action name.');
    }
    $type = $entity->getEntityTypeId() === 'node' ? 'content' : $entity->getEntityTypeId();
    return "{$action} {$entity->bundle()} {$type}";
  }

  /**
   * {@inheritdoc}
   */
  public function getExcludedParent(ShareableContentInterface $entity): ?GroupInterface {
    return $entity instanceof GroupContentInterface && $entity->hasGroup()
      ? $entity->getGroup()
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAlreadySharedGroupIds(ShareableContentInterface $entity): array {
    return array_column($entity->getSharedInField()->getValue(), 'target_id');
  }

}
