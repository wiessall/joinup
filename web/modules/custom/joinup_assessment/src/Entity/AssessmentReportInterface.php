<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Entity;

use Drupal\joinup_core\Entity\PermalinkInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for AssessmentReport entities in Joinup.
 */
interface AssessmentReportInterface extends NodeInterface, GroupContentInterface, PermalinkInterface {}
