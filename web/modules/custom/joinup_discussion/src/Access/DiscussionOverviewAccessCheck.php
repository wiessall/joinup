<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\joinup_discussion\JoinupDiscussionGroupInspectorInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;

/**
 * Provides a route access checker for _joinup_discussion_overview.
 */
readonly class DiscussionOverviewAccessCheck implements AccessInterface {

  public function __construct(
    protected JoinupGroupManagerInterface $groupManager,
    protected JoinupDiscussionGroupInspectorInterface $groupInspector,
  ) {
  }

  /**
   * Checks access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(): AccessResultInterface {
    $group = $this->groupManager->getGroupFromContext();
    if (!$group) {
      return AccessResult::forbidden('Not a group page');
    }

    return AccessResult::allowedIf($group->isPublished() && $this->groupInspector->groupHasDiscussions($group))
      ->addCacheableDependency($group)
      ->addCacheTags(Cache::buildTags('og-group-content', $group->getCacheTags()))
      ->addCacheContexts(['og_group_context']);
  }

}
