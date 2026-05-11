<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\assessments\Event\AssessmentReportEvent;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Drupal\joinup_notification\MessageArgumentGenerator;
use Drupal\og\OgMembershipInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;

/**
 * Service for sending assessment report notifications.
 */
final readonly class AssessmentReportNotification implements AssessmentReportNotificationInterface {

  const string QUEUE_NAME = 'assessment_report_notify_members';

  public function __construct(
    protected JoinupMessageDeliveryInterface $messageDelivery,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
    protected QueueFactory $queueFactory,
    protected AccountProxyInterface $currentUser,
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
  public function notifyReportOwnerPublished(AssessmentReportEvent $event): void {
    $this->messageDelivery->sendMessageTemplateToUser(
      'assessment_report_published',
      $this->buildArguments($event),
      $event->getEntity()->getOwner()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function queueMembersPublishedNotification(AssessmentReportEvent $event): void {
    $queue = $this->queueFactory->get(self::QUEUE_NAME);

    $queue->createQueue();
    $queue->createItem([
      'entity_id' => $event->getEntity()->id(),
      'actor_id' => $this->currentUser->id(),
    ]);
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
   * {@inheritdoc}
   */
  public function notifySubscribedMembers(AssessmentInterface $entity, UserInterface $publisher): void {
    $collection = $this->loadCollection();
    if (!$collection) {
      return;
    }

    $users = $this->getSubscribedUsers($collection);
    if (empty($users)) {
      return;
    }

    $arguments = MessageArgumentGenerator::getActorArguments($publisher)
      + $this->buildEntityArguments($entity)
      + MessageArgumentGenerator::getGroupArguments($collection);

    foreach ($users as $user) {
      $this->messageDelivery->sendMessageTemplateToUser(
        'assessment_report_members',
        $arguments,
        $user,
      );
    }
  }

  /**
   * Loads the fixed Interoperability assessments space collection node.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The collection node, or NULL if not found.
   */
  protected function loadCollection(): ?EntityInterface {
    $nodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['uuid' => AssessmentInterface::GROUP_UUID]);
    $collection = reset($nodes);
    return $collection ?: NULL;
  }

  /**
   * Returns users with an active subscription to assessment_report content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $collection
   *   The collection node.
   *
   * @return \Drupal\user\UserInterface[]
   *   Subscribed active members, keyed by user ID.
   */
  protected function getSubscribedUsers(EntityInterface $collection): array {
    $membershipStorage = $this->entityTypeManager->getStorage('og_membership');
    $membershipIds = $membershipStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'node')
      ->condition('entity_id', $collection->id())
      ->condition('state', OgMembershipInterface::STATE_ACTIVE)
      ->condition('announcements', 1)
      ->execute();

    if (empty($membershipIds)) {
      return [];
    }

    $memberships = $membershipStorage->loadMultiple($membershipIds);
    $userIds = array_map(
      fn(OgMembershipInterface $membership): int => (int) $membership->getOwnerId(),
      $memberships
    );

    return $this->entityTypeManager->getStorage('user')->loadMultiple($userIds);
  }

  /**
   * Builds entity-based arguments for message templates.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment report entity.
   *
   * @return array
   *   Entity arguments array for the message template.
   */
  protected function buildEntityArguments(AssessmentInterface $entity): array {
    return [
      '@assessment:title' => $entity->label(),
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
    ];
  }

  /**
   * Builds arguments array for message templates, including the update reason.
   *
   * @param \Drupal\assessments\Event\AssessmentReportEvent $event
   *   The assessment report event.
   *
   * @return array
   *   Complete arguments array for the message template.
   */
  protected function buildArguments(AssessmentReportEvent $event): array {
    return $this->buildEntityArguments($event->getEntity()) + [
      '@assessment:require_update_reason' => $event->getReason() ?? '',
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
    $uids = $userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', $roleIds, 'IN')
      ->execute();

    if (empty($uids)) {
      return [];
    }

    return $userStorage->loadMultiple($uids);
  }

}
