<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Custom access check for group settings canonical page.
 */
class GroupSettingsViewAccessCheck {

  /**
   * Custom access check for group settings meta entity.
   */
  public static function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $meta_entity = $route_match->getParameter('meta_entity');
    $membershipManager = \Drupal::service('Drupal\joinup_group\JoinupGroupPermissionManagerInterface');
    $bundles = ['collection_settings', 'solution_settings'];
    $group = $meta_entity->getTargetEntity();
    if ($meta_entity instanceof MetaEntityInterface && in_array($meta_entity->bundle(), $bundles, TRUE) && $group instanceof GroupInterface) {
      if ($meta_entity->access('update', $account, TRUE)->isAllowed() ||
        $membershipManager->userHasOgPermission($account->id(), $group, 'edit any solution content')) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
