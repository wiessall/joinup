<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Drupal\views\Views;
use Symfony\Component\Routing\Route;

/**
 * Provides a route access checker for _joinup_group_results_required route
 * requirement.
 */
class ResultsRequiredAccessCheck implements AccessInterface {

  /**
   * Builds a new access checker instance.
   *
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager
   *   Group manager.
   */
  public function __construct(protected JoinupGroupManagerInterface $groupManager) {
  }

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $routeMatch): AccessResultInterface {
    $viewId = $route->getDefault('view_id');
    $displayId = $route->getDefault('display_id');
    $group = $this->groupManager->getGroupFromContext();

    if (empty($viewId) || empty($displayId) || !$group) {
      return AccessResult::forbidden('Not a view page');
    }

    $view = Views::getView($viewId);
    $view->execute($displayId);

    return AccessResult::allowedIf($group->isPublished() && $view->total_rows > 0)
      ->addCacheTags(Cache::buildTags('og-group-content', $group->getCacheTags()))
      ->addCacheContexts(['og_group_context']);
  }

}
