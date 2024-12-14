<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Entity;

use Drupal\joinup_core\Entity\PermalinkTrait;
use Drupal\node\Entity\Node;

/**
 * Entity subclass for the 'assessment_report' bundle.
 */
class AssessmentReport extends Node implements AssessmentReportInterface {

  use PermalinkTrait;

}
