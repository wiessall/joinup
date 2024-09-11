<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\Entity;

use Drupal\comment\Entity\Comment;
use Drupal\joinup_core\Entity\PermalinkInterface;
use Drupal\joinup_core\Entity\PermalinkTrait;

/**
 * Entity subclass for the 'comment'.
 */
class JoinupCommunityContentComment extends Comment implements PermalinkInterface {

  use PermalinkTrait;

}
