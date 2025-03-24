<?php

declare(strict_types=1);

namespace Drupal\topic\Entity;

use Drupal\joinup\Traits\TopicTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for topic terms.
 */
class Topic extends Term implements TopicInterface {
  use TopicTrait;

}
