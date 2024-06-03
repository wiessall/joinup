<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessCheck;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgRoleInterface;
use Drupal\replicate_ui\ReplicateAccessChecker;
use Drupal\user\Access\PermissionAccessCheck;
use Symfony\Component\Routing\Route;

/**
 * Overrides ReplicateAccessChecker.
 *
 * Allows access if account has the replicate permission, or if it is the owner
 * or the facilitator of the group of the entity.
 */
class JoinupReplicateAccessChecker extends ReplicateAccessChecker {

  public function __construct(
    PermissionAccessCheck $permAccessChecker,
    EntityAccessCheck $entityAccessChecker,
    protected MembershipManagerInterface $membershipManager,
  ) {
    parent::__construct($permAccessChecker, $entityAccessChecker);
  }

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $entityTypeId = $route->getDefault('entity_type_id');
    $entity = $route_match->getParameters()->get($entityTypeId);
    $membershipRoleNames = $entity instanceof GroupContentInterface && $entity->hasGroup()
      ? array_map(
        fn (OgRoleInterface $role): string => $role->getName(),
        $this->membershipManager->getMembership($entity->getGroup(), $account->id())?->getRoles() ?? []
      )
      : [];

    return AccessResult::allowedIf((bool) array_intersect(
      ['administrator', 'facilitator'],
      $membershipRoleNames,
    ))->orIf(AccessResult::allowedIfHasPermission($account, 'replicate entities'));
  }

}
