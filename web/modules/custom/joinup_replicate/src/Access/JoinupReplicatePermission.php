<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;

/**
 * Enhanced permission access check.
 *
 * Determines access to routes based on an account role permission, but also
 * taking Organic Group membership permissions.
 */
class JoinupReplicatePermission implements AccessInterface {

  public function __construct(
    protected MembershipManagerInterface $membershipManager,
  ) {}

  /**
   * Checks if the user has a permission (either by Drupal role or by OG).
   *
   * If the route parameter names don't have {entity_type_id} or {entity_id} you
   * can still use this access check, by passing the "entity_type_id" definition
   * using the Route::setOption method.
   * see \Drupal\og\Routing\RouteSubscriber::alterRoutes as an example.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The active user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, RouteMatchInterface $routeMatch): AccessResultInterface {
    $permission = $routeMatch->getRouteObject()->getRequirement('_joinup_replicate_permission');
    $accessResult = AccessResult::allowedIfHasPermission($account, $permission);
    $entity = static::getContentEntityFromRoute($routeMatch);
    $group = static::getGroup($entity);
    $membership = $group instanceof GroupInterface
      ? $this->membershipManager->getMembership($group, $account->id())
      : NULL;

    if ($entity) {
      $accessResult->addCacheableDependency($entity);
    }
    if ($group instanceof GroupInterface) {
      $accessResult->addCacheableDependency($group);
    }

    if ($membership instanceof OgMembershipInterface) {
      $accessResult->addCacheableDependency($membership);
      $ogPermissionAccessResult = AccessResult::allowedIf($membership->hasPermission($permission));
      return $accessResult->orIf($ogPermissionAccessResult);
    }

    return $accessResult;
  }

  /**
   * Returns the content entity from route match, if any.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match to check.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity from the route match, if any.
   */
  protected static function getContentEntityFromRoute(RouteMatchInterface $routeMatch): ?ContentEntityInterface {
    // First, check if we have an entity_type_id parameter.
    $entityTypeId = $routeMatch->getParameter('entity_type_id');
    $candidate = is_string($entityTypeId)
      ? $routeMatch->getParameter($entityTypeId)
      : NULL;
    if ($candidate instanceof ContentEntityInterface) {
      return $candidate;
    }

    // Fall back to checking each route parameters.
    foreach ($routeMatch->getParameters()->all() as $candidate) {
      if (!$candidate instanceof ContentEntityInterface) {
        continue;
      }
      return $candidate;
    }

    return NULL;
  }

  /**
   * Returns OG group from the given variable.
   *
   * @param mixed $entity
   *   The thing to fetch the OG group from.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The group from the thing, or NULL.
   */
  protected static function getGroup(mixed $entity): ?GroupInterface {
    if ($entity instanceof GroupInterface) {
      return $entity;
    }

    return $entity instanceof GroupContentInterface && $entity->hasGroup()
      ? $entity->getGroup()
      : NULL;
  }

}
