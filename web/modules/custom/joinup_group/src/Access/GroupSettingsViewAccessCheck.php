<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
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
    $bundles = ['collection_settings', 'solution_settings'];
    if ($meta_entity instanceof MetaEntityInterface && in_array($meta_entity->bundle(), $bundles, TRUE)) {
      return $meta_entity->access('update', $account, TRUE);
    }
    return AccessResult::allowed();
  }

}
