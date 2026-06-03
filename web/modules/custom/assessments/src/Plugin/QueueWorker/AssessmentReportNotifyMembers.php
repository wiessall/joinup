<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\AssessmentReportNotification;
use Drupal\assessments\AssessmentReportNotificationInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends the assessment_report_members notification to subscribed members.
 */
#[QueueWorker(
  id: AssessmentReportNotification::QUEUE_NAME,
  title: new TranslatableMarkup('Assessment report members notification'),
  cron: ['time' => 30],
)]
class AssessmentReportNotifyMembers extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly AssessmentReportNotificationInterface $notificationService,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(AssessmentReportNotificationInterface::class),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity = $this->entityTypeManager->getStorage('assessment')
      ->load($data['entity_id']);
    $publisher = $this->entityTypeManager->getStorage('user')
      ->load($data['actor_id']);

    if (!$entity instanceof AssessmentInterface || !$publisher instanceof UserInterface) {
      return;
    }

    $this->notificationService->notifySubscribedMembers($entity, $publisher);
  }

}
