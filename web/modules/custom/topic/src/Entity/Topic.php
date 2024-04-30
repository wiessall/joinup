<?php

declare(strict_types=1);

namespace Drupal\topic\Entity;

use Drupal\joinup_taxonomy\Entity\MappedTerm;

/**
 * Bundle class for topic terms.
 */
class Topic extends MappedTerm implements TopicInterface {}
