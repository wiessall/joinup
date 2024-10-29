<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Reusable code that checks access to group announcements page.
 */
trait AnnouncementsAccessTrait {

  /**
   * The Joinup Group manager service.
   */
  protected JoinupGroupManagerInterface $groupManager;

  /**
   * Injects the Joinup group manager service.
   *
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $group_manager
   *   The Joinup Group manager service.
   *
   * @return $this
   */
  public function setJoinupGroupManager(JoinupGroupManagerInterface $group_manager): self {
    $this->groupManager = $group_manager;
    return $this;
  }

  /**
   * Checks the access to group announcements page.
   *
   * All the following checks should pass:
   * - The group is published.
   * - The announcements are enabled for this group.
   * - The current user has either the site-wide or the group 'manage group
   *   announcements' permission.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  protected function accessAnnouncements(GroupInterface $group): AccessResultInterface {
    $access = AccessResult::allowedIf($group->isPublished())->addCacheableDependency($group);

    $bundle = "{$group->bundle()}_settings";
    $meta_entity = MetaEntity::loadOrCreate($bundle, $group);
    $enabled = (bool) $meta_entity->get('announcements_enabled')->value;
    $access = $access->andIf(AccessResult::allowedIf($enabled)->addCacheTags([
      "meta_entity_list:$bundle",
    ]));

    return $access->andIf($this->groupManager->access($group, 'manage group announcements', 'manage group announcements'));
  }

}
