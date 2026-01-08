<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;

/**
 * Service for sending assessment report notifications.
 */
final readonly class AssessmentReportNotification implements AssessmentReportNotificationInterface {

  public function __construct(
    protected JoinupMessageDeliveryInterface $messageDelivery,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function notifyReportSubmitted(AssessmentReportEvent $event): void {
    $moderators = $this->getModerators();

    if (empty($moderators)) {
      return;
    }

    $this->messageDelivery->sendMessageTemplateToMultipleUsers(
      'assessment_report_submitted',
      $this->buildArguments($event),
      $moderators
    );
  }

  /**
   * {@inheritdoc}
   */
  public function notifyReportPublished(AssessmentReportEvent $event): void {
    $this->messageDelivery->sendMessageTemplateToUser(
      'assessment_report_published',
      $this->buildArguments($event),
      $event->getEntity()->getOwner()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function notifyReportRequiresUpdates(AssessmentReportEvent $event): void {
    $this->messageDelivery->sendMessageTemplateToUser(
      'assessment_report_updates',
      $this->buildArguments($event),
      $event->getEntity()->getOwner()
    );
  }

  /**
   * Builds arguments array for message templates.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   *
   * @return array
   *   Complete arguments array for the message template.
   */
  protected function buildArguments(AssessmentReportEvent $event): array {
    $entity = $event->getEntity();
    $reason = $event->getReason() ?? '';

    return [
      '@assessment:id' => $entity->id(),
      '@assessment:changed' => $this->dateFormatter
        ->format($entity->getChangedTime(), 'medium'),
      '@assessment:user' => $entity->getOwner()->getDisplayName(),
      '@assessment:report_url' => $entity->toUrl()->setAbsolute()->toString(),
      '@assessment:moderate_url' => Url::fromRoute('assessments.moderate', [
        'assessment_type' => $entity->bundle(),
        'assessment' => $entity->id(),
      ])->setAbsolute()->toString(),
      '@assessment:edit_url' => $entity->toUrl('edit-form')->setAbsolute()->toString(),
      '@assessment:require_update_reason' => $reason,
    ];
  }

  /**
   * Gets all users with moderate assessment report permission.
   *
   * @return \Drupal\user\UserInterface[]
   *   Array of user entities with moderation permissions.
   */
  protected function getModerators(): array {
    $user_storage = $this->entityTypeManager->getStorage('user');
    $role_storage = $this->entityTypeManager->getStorage('user_role');

    $roles = $role_storage->loadMultiple();
    $role_ids = [];
    foreach ($roles as $role) {
      if ($role->hasPermission('moderate assessment report')) {
        $role_ids[] = $role->id();
      }
    }

    if (empty($role_ids)) {
      return [];
    }

    // Load all active users with these roles.
    $query = $user_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', $role_ids, 'IN');

    $uids = $query->execute();

    if (empty($uids)) {
      return [];
    }

    return $user_storage->loadMultiple($uids);
  }

}
