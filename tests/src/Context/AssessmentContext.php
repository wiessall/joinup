<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Step definitions for assessments checks.
 */
class AssessmentContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Navigates to the latest assessment.
   *
   * @When I go to the latest document assessment
   */
  public function visitLatestDocumentAssessment(): void {
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'document')
      ->condition('title', 'Online form for assessments demo: Submission' . '%', 'LIKE')
      ->sort('created', 'DESC')
      ->range(0, 1);

    $nids = $query->execute();
    if (empty($nids)) {
      throw new \Exception('The latest assessment was not found.');
    }

    $node = $this->getEntityById('node', reset($nids));
    $this->visitPath($node->toUrl()->toString());
  }

}
