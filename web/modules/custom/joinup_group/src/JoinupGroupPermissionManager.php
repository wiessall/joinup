<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Entity\EntityInterface;
use Drupal\og\MembershipManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Helper methods related to the Joinup groups.
 */
readonly class JoinupGroupPermissionManager implements JoinupGroupPermissionManagerInterface {

  public function __construct(
    #[Autowire(service: 'og.membership_manager')]
    protected MembershipManagerInterface $membershipManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function userHasOgCollectionPermission(int|string $uid, EntityInterface $group, string $permission): bool {
    $membership = $this->membershipManager->getMembership($group, $uid);
    foreach ($membership->getRoles() as $role) {
      if ($role->hasPermission($permission)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
