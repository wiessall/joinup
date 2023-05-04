<?php

declare(strict_types = 1);

namespace Drupal\joinup_discussion\Entity;

use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;

/**
 * Interface for discussion entities in Joinup.
 */
interface DiscussionInterface extends CommunityContentInterface, ArchivableEntityInterface {

  /**
   * Gets the discussion latest update time timestamp.
   *
   * @return int
   *   Latest update timestamp of the discussion.
   */
  public function getLatestUpdateTime(): int;

}
