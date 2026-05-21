<?php

declare(strict_types=1);

namespace Drupal\assessments\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\AssessmentReport;

/**
 * Hooks related to the published_at functionality.
 */
class PublishedAtHook {

  public function __construct(protected readonly TimeInterface $time) {}

  #[Hook('assessment_insert')]
  #[Hook('assessment_update')]
  public function setPublicationDate(AssessmentInterface $report): void {
    if ($report instanceof AssessmentReport) {
      return;
    }

    $field = $report->get('published_at');

    if ($field->isEmpty() && $report->isPublished()) {
      $field->set(0, ['value' => $this->time->getRequestTime()]);
    }
  }

}
