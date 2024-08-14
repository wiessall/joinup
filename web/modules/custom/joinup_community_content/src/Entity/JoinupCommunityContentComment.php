<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\Entity;

use Drupal\comment\Entity\Comment;
use Drupal\joinup_core\Entity\PermalinkInterface;

/**
 * Entity subclass for the 'comment'.
 */
class JoinupCommunityContentComment extends Comment implements PermalinkInterface {

  /**
   * {@inheritdoc}
   */
  public function getPermalink(): ?string {
    if (!$this->in_preview) {
      return $this->permalink()->toString();
    }
    return NULL;
  }

}
