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
   * Event for assessment report published via the moderation form.
   *
   * Dispatched from the moderation form when a moderator publishes a report.
   *
   * @Event
   *
   * @var string
   */
  const string ASSESSMENT_REPORT_MODERATION_PUBLISHED = 'assessments.report.moderation_published';

  /**
   * Event for assessment report state transition to published.
   *
   * Dispatched on any publish path (moderation form, API, direct creation).
   *
   * @Event
   *
   * @var string
   */
  const string ASSESSMENT_REPORT_STATE_PUBLISHED = 'assessments.report.state_published';

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
