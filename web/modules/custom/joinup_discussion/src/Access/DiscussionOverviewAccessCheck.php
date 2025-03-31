<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
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
    protected Connection $database,
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

    // Check how many discussions the group has.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $childDiscussionCount = (int) $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'discussion')
      ->condition('og_audience', $group->id())
      ->condition('status', TRUE)
      ->count()
      ->execute();

    // Check number of discussions shared with the group. Doing this with SQL
    // because it is impossible to join with entity query.
    $sharedDiscussionCount = NULL;
    if (!$childDiscussionCount) {
      $metaEntityStorage = $this->entityTypeManager->getStorage('meta_entity');
      assert($metaEntityStorage instanceof SqlEntityStorageInterface);
      $metaTableMapping = $metaEntityStorage->getTableMapping();
      assert($nodeStorage instanceof SqlEntityStorageInterface);
      $nodeTableMapping = $nodeStorage->getTableMapping();

      // Start with the shared in field of the shared in meta entity, and
      // limit results to the records pointing to the current group.
      $sharedDiscussionCountQuery = $this->database->select($metaTableMapping->getFieldTableName('field_shared_in'), 'fsi')
        ->condition('fsi.bundle', 'shared_in')
        ->condition('fsi.field_shared_in_target_id', $group->id());

      // Join meta entity base table; this is where the target of the meta
      // entity is stored.
      $sharedDiscussionCountQuery->join(
        $metaTableMapping->getFieldTableName('target'),
        'me',
        'me.id = fsi.entity_id'
      );

      // Join the node table too, and limit the results to those "shares" which
      // belong to a discussion node.
      $sharedDiscussionCountQuery->join(
        $nodeTableMapping->getFieldTableName('type'),
        'nfd',
        'nfd.nid = me.target__target_id'
      );
      $sharedDiscussionCountQuery
        ->condition('nfd.type', 'discussion')
        ->addExpression("COUNT(me.target__target_id)", 'count');

      $sharedDiscussionCount = $sharedDiscussionCountQuery->execute()->fetchField();
    }

    $significantCount = $sharedDiscussionCount ?? $childDiscussionCount;

    return AccessResult::allowedIf($group->isPublished() && $significantCount > 0)
      ->addCacheTags(Cache::buildTags('og-group-content', $group->getCacheTags()))
      ->addCacheContexts(['og_group_context']);
  }

}
