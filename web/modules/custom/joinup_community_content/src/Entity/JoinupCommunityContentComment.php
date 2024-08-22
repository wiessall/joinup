<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\Entity;

use Drupal\Core\Url;
use Drupal\comment\Entity\Comment;
use Drupal\joinup_core\Entity\PermalinkInterface;

/**
 * Entity subclass for the 'comment'.
 */
class JoinupCommunityContentComment extends Comment implements PermalinkInterface {

  /**
   * {@inheritdoc}
   */
  public function getPermalink(): ?Url {
    try {
      return $this->permalink();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
