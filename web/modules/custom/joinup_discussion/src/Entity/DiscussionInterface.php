<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Entity;

use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;

/**
 * Interface for discussion entities in Portal.
 */
interface DiscussionInterface extends CommunityContentInterface, ArchivableEntityInterface, LogoInterface {

  /**
   * Gets the discussion latest update time timestamp.
   *
   * @return int
   *   Latest update timestamp of the discussion.
   */
  public function getLatestUpdateTime(): int;

}
