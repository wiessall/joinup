<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides a route access checker for _joinup_discussion_overview.
 */
readonly class DiscussionOverviewAccessCheck implements AccessInterface {

  public function __construct(
    protected JoinupGroupManagerInterface $groupManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
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
    $group = $this->groupManager->getGroupFromContext();
    if (!$group) {
      return AccessResult::forbidden('Not a group page');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $queryStorage = $storage->getQuery();

    $orCondition = $queryStorage->orConditionGroup();
    $orCondition->condition('og_audience', $group->id());
    $orCondition->condition('field_shared_in', [$group->id()], 'IN');

    $count = $queryStorage
      ->accessCheck(FALSE)
      ->condition('type', 'discussion')
      ->condition($orCondition)
      ->condition('status', TRUE)
      ->count()
      ->execute();

    return AccessResult::allowedIf($group->isPublished() && $count > 0)
      ->addCacheTags(Cache::buildTags('og-group-content', $group->getCacheTags()))
      ->addCacheContexts(['og_group_context']);
  }

}
