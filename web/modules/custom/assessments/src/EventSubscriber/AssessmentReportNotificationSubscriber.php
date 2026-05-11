<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Drupal\assessments\AssessmentReportNotificationInterface;
use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\assessments\NotificationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for assessment report notifications.
 */
final readonly class AssessmentReportNotificationSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected AssessmentReportNotificationInterface $notificationService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      NotificationEvents::ASSESSMENT_REPORT_SUBMITTED_FOR_REVIEW => ['onReportSubmitted'],
      NotificationEvents::ASSESSMENT_REPORT_MODERATION_PUBLISHED => ['onReportModerationPublished'],
      NotificationEvents::ASSESSMENT_REPORT_STATE_PUBLISHED => ['onReportStatePublished'],
      NotificationEvents::ASSESSMENT_REPORT_REQUIRE_UPDATES => ['onReportRequireUpdates'],
    ];
  }

  /**
   * Sends notification when a report is submitted for review.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function onReportSubmitted(AssessmentReportEvent $event): void {
    $this->notificationService->notifyReportSubmitted($event);
  }

  /**
   * Sends notification when a report is published via the moderation form.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function onReportModerationPublished(AssessmentReportEvent $event): void {
    $this->notificationService->notifyReportOwnerPublished($event);
  }

  /**
   * Queues members notification when a report transitions to published state.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function onReportStatePublished(AssessmentReportEvent $event): void {
    $this->notificationService->queueMembersPublishedNotification($event);
  }

  /**
   * Sends notification when a report requires updates.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function onReportRequireUpdates(AssessmentReportEvent $event): void {
    $this->notificationService->notifyReportRequiresUpdates($event);
  }

}
