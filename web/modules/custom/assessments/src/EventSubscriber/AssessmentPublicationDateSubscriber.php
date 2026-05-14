<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\assessments\NotificationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for assessment report publication date.
 */
final readonly class AssessmentPublicationDateSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      NotificationEvents::ASSESSMENT_REPORT_PUBLISHED => ['onReportPublished'],
    ];
  }

  /**
   * Sets the publication date value.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   */
  public function onReportPublished(AssessmentReportEvent $event): void {
    $entity = $event->getEntity();
    $entity->set('published_at', time());
    $entity->save();
  }

}
