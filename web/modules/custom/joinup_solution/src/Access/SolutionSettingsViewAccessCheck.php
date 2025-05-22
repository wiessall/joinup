<?php

namespace Drupal\joinup_solution\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

class SolutionSettingsViewAccessCheck {

  /**
   * Custom access check for solution_settings meta entity.
   */
  public static function access(RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    $meta_entity = $route_match->getParameter('meta_entity');
    if ($meta_entity instanceof MetaEntityInterface && $meta_entity->bundle() === 'solution_settings') {
      return $meta_entity->access('update', $account, TRUE);
    }
    return AccessResult::allowed();
  }

}

