<?php

declare(strict_types=1);

namespace Drupal\joinup_log\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\joinup_group\Event\GroupReport;
use Drupal\joinup_group\Event\GroupReportsEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Group report subscriber.
 *
 * Implements the EventSubscriberInterface for adding
 * the action log report to group reports.
 */
class ActionsLogGroupReportSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      GroupReportsEventInterface::EVENT_NAME => ['onGroupReportsCollection'],
    ];
  }

  /**
   * Provides the actions log report.
   *
   * @param \Drupal\joinup_group\Event\GroupReportsEventInterface $event
   *   The group reports event.
   *
   * @see \Drupal\joinup_subscription\Controller\SubscribersReportController
   */
  public function onGroupReportsCollection(GroupReportsEventInterface $event): void {
    $event->addGroupReport(new GroupReport(
      'actions-log',
      $this->t('Actions log'),
      Url::fromRoute('view.actions_log.logs', [
        'rdf_entity' => $event->getGroup()->id(),
      ]),
      NULL
    ));
  }

}
