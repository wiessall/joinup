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
   * Sends a published notification to the report owner.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function notifyReportOwnerPublished(AssessmentReportEvent $event): void;

  /**
   * Sends notification when a report requires updates.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function notifyReportRequiresUpdates(AssessmentReportEvent $event): void;

  /**
   * Queues a published notification for subscribed collection members.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function queueMembersPublishedNotification(AssessmentReportEvent $event): void;

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
