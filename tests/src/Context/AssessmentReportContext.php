<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Step definitions for assessment report entities.
 */
class AssessmentReportContext extends RawDrupalContext {

  use EntityTrait;
  use TestingEntitiesTrait;
  use UtilityTrait;

  /**
   * Navigates to the moderate form of a specific assessment report.
   *
   * @param string $title
   *   The title of the assessment report.
   *
   * @When I go to the moderate form of the :title assessment report
   */
  public function visitAssessmentReportModerateForm(string $title): void {
    $entity = $this->getEntityByLabel('assessment', $title, 'report');
    $url = Url::fromRoute('entity.assessment.moderate_form', [
      'assessment' => $entity->id(),
    ]);
    $this->visitPath($url->toString());
  }

}