<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Provides node access grant IDs for groups.
 *
 * Group facilitators have the 'view any unpublished content' permission within
 * their groups. In order to make this work we provide a node access grant for
 * the 'joinup_group_view_unpublished' realm.
 *
 * The node access grants work with numeric grant IDs while our groups have
 * string IDs, so we store a mapping in the 'joinup_group_node_access' table.
 * This service provides the mapping data.
 */
class JoinupGroupNodeAccessGrants implements JoinupGroupNodeAccessGrantsInterface {

  /**
   * The SQL connection class for the primary database storage.
   */
  protected Connection $sqlConnection;

  /**
   * The memory cache backend.
   */
  protected CacheBackendInterface $memoryCache;

  /**
   * Constructs a JoinupGroupNodeAccessGrants service.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The SQL connection class for the primary database storage.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The memory cache backend.
   */
  public function __construct(Connection $connection, CacheBackendInterface $cache) {
    $this->sqlConnection = $connection;
    $this->memoryCache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeAccessGrantId(GroupInterface $group): int {
    if ($group->isNew()) {
      throw new \InvalidArgumentException('Cannot provide an access grant ID for an unsaved group.');
    }

    $cid = "{$group->getEntityTypeId()}:{$group->id()}";

    // Use cached result if it exists.
    if (!$gid = $this->memoryCache->get($cid)->data ?? NULL) {
      try {
        if ($group->isNew()) {
          throw new \InvalidArgumentException('Cannot create an access grant record for an unsaved group.');
        }
        $gid = (int) $this->sqlConnection->insert('joinup_group_node_access')
          ->fields(
            ['entity_type', 'entity_id'],
            [$group->getEntityTypeId(), $group->id()]
          )->execute();
      }
      catch (IntegrityConstraintViolationException $exception) {
        // Instead of running first a SELECT, in order to check the existence of
        // this record, we try an INSERT to avoid any race condition that might
        // occur between SELECT and INSERT.
        $gid = (int) $this->sqlConnection->select('joinup_group_node_access', 'na')
          ->fields('na', ['gid'])
          ->condition('entity_type', $group->getEntityTypeId())
          ->condition('entity_id', $group->id())
          ->execute()
          ->fetchField();
      }
      $this->memoryCache->set($cid, $gid, Cache::PERMANENT, $group->getCacheTagsToInvalidate());
    }

    return $gid;
  }

}
