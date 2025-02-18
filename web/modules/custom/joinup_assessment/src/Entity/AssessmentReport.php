<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Entity;

use Drupal\collection\Entity\NodeCollectionContentTrait;
use Drupal\joinup_core\Entity\PermalinkTrait;
use Drupal\node\Entity\Node;

/**
 * Entity subclass for the 'assessment_report' bundle.
 */
class AssessmentReport extends Node implements AssessmentReportInterface {

  use NodeCollectionContentTrait;
  use PermalinkTrait;

  /**
   * Returns the group reference field name.
   *
   * @return string
   *   The group reference field name.
   */
  protected function getGroupFieldName(): string {
    return 'og_audience';
  }

}
