<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;

/**
 * Group inspector service.
 */
class JoinupDiscussionGroupInspector implements JoinupDiscussionGroupInspectorInterface {

  public function __construct(
    protected JoinupGroupManagerInterface $groupManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function groupHasDiscussions(GroupInterface $group): bool {
    // Check how many discussions the group has.
    $childDiscussionCount = $this->getChildDiscussionCount($group);

    // Check number of discussions shared with the group only if there are no
    // discussion which are directly belong to the group.
    $sharedDiscussionCount = !$childDiscussionCount
      ? $this->getDiscussionsCountSharedWithGroup($group)
      : NULL;

    return (bool) ($sharedDiscussionCount ?? $childDiscussionCount);
  }

  /**
   * Returns how many discussions the group has.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group.
   *
   * @return int
   *   Number of discussions belonging to the specified group.
   */
  protected function getChildDiscussionCount(GroupInterface $group): int {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    return (int) $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'discussion')
      ->condition('og_audience', $group->id())
      ->condition('status', TRUE)
      ->count()
      ->execute();
  }

  /**
   * Returns how many discussions are shared to the specified group.
   *
   * The data is stored in the tables of two different entities, so it is
   * impossible to use a single entity query. Because there might be quite lot
   * shared entities, and we're only interested in the sharings of discussions
   * belonging to this group, fetching all the sharings might require lot memory
   * – so we use raw SQL queries here to keep resource requirements low.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group.
   *
   * @return int
   *   Number of discussions shared with the specified group.
   */
  protected function getDiscussionsCountSharedWithGroup(GroupInterface $group): int {
    $metaEntityStorage = $this->entityTypeManager->getStorage('meta_entity');
    assert($metaEntityStorage instanceof SqlEntityStorageInterface);
    $metaTableMapping = $metaEntityStorage->getTableMapping();

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    assert($nodeStorage instanceof SqlEntityStorageInterface);
    $nodeTableMapping = $nodeStorage->getTableMapping();

    // Start with the shared in field of the shared in meta entity, and
    // limit results to the records pointing to the current group.
    $sharedDiscussionCountQuery = $this->database
      ->select($metaTableMapping->getFieldTableName('field_shared_in'), 'fsi')
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

    return (int) $sharedDiscussionCountQuery->execute()->fetchField();
  }

}
