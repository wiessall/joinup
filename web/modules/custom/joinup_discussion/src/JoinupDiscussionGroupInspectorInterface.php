<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion;

use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Interface og the group inspector service.
 */
interface JoinupDiscussionGroupInspectorInterface {

  /**
   * Determines whether the group has any discussions.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group to check.
   *
   * @return bool
   *   TRUE if the group has any discussion, included discussions shared to this
   *   group, FALSE otherwise.
   */
  public function groupHasDiscussions(GroupInterface $group): bool;

}
