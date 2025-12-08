<?php

declare(strict_types=1);

namespace Drupal\assessments;

/**
 * Define events for the assessments module.
 */
final class NotificationEvents {

  /**
   * Event for assessment report submitted for review.
   *
   * Dispatched when an assessment report is submitted for review.
   *
   * @Event
   *
   * @var string
   */
  const string ASSESSMENT_REPORT_SUBMITTED_FOR_REVIEW = 'assessments.report.submitted_for_review';

  /**
   * Event for assessment report published.
   *
   * Dispatched when an assessment report is published by a moderator.
   *
   * @Event
   *
   * @var string
   */
  const string ASSESSMENT_REPORT_PUBLISHED = 'assessments.report.published';

  /**
   * Event for assessment report requiring updates.
   *
   * Dispatched when a moderator requests updates to an assessment report.
   *
   * @Event
   *
   * @var string
   */
  const string ASSESSMENT_REPORT_REQUIRE_UPDATES = 'assessments.report.require_updates';

}
