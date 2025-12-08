<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\assessments\Event\AssessmentReportEvent;

/**
 * Interface for assessment report notification services.
 */
interface AssessmentReportNotificationInterface {

  /**
   * Sends notification when a report is submitted for review.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function notifyReportSubmitted(AssessmentReportEvent $event): void;

  /**
   * Sends notification when a report is published.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function notifyReportPublished(AssessmentReportEvent $event): void;

  /**
   * Sends notification when a report requires updates.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function notifyReportRequiresUpdates(AssessmentReportEvent $event): void;

}
