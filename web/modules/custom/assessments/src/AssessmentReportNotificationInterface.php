<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\user\UserInterface;

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

  /**
   * Queues a members notification for the given assessment report.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The published assessment report entity.
   */
  public function queueMembersNotification(AssessmentInterface $entity): void;

  /**
   * Sends the assessment_report_members notification to subscribed members.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The published assessment report entity.
   * @param \Drupal\user\UserInterface $publisher
   *   The moderator who published the report.
   */
  public function notifySubscribedMembers(AssessmentInterface $entity, UserInterface $publisher): void;

}
