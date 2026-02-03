<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\assessments\Entity\AssessmentReport;

/**
 * Defines the interface for Assessment Statement entities.
 */
interface AssessmentStatementInterface {

  /**
   * Returns the report that the statement is linked to.
   *
   * @return \Drupal\assessments\Entity\AssessmentReport|null
   *   The report entity that the statement is linked to, or NULL if none
   *   exists.
   */
  public function getRelevantReport(): ?AssessmentReport;

}
