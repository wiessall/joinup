<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Drupal\user\RoleInterface;

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
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    // Filter roles with the permission, excluding admin roles.
    $roleIds = array_keys(array_filter(
      $roles,
      fn($role): bool => $role instanceof RoleInterface
        && !$role->isAdmin()
        && $role->hasPermission('moderate assessment report')
    ));

    if (empty($roleIds)) {
      return [];
    }

    // Load all active users with these roles.
    $userStorage = $this->entityTypeManager->getStorage('user');
    $query = $userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', $roleIds, 'IN');

    $uids = $query->execute();

    if (empty($uids)) {
      return [];
    }

    return $userStorage->loadMultiple($uids);
  }

}
