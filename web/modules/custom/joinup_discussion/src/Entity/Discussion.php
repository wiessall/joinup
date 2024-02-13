<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Entity;

use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_community_content\Entity\CommunityContentBase;
use Drupal\joinup_workflow\ArchivableEntityTrait;

/**
 * Entity subclass for the 'discussion' bundle.
 */
class Discussion extends CommunityContentBase implements DiscussionInterface {

  use ArchivableEntityTrait;
  use LogoTrait;

  /**
   * {@inheritdoc}
   */
  public function getLogoFieldName(): string {
    return 'field_discussion_logo';
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestUpdateTime(): int {
    return (int) max(
      $this->getCreatedTime(),
      $this->getChangedTime(),
      $this->get('field_replies')->last_comment_timestamp
    );
  }

}
