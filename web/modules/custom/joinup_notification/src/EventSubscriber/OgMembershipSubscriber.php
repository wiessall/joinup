<?php

declare(strict_types=1);

namespace Drupal\joinup_notification\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Drupal\joinup_notification\EntityMessageHelper;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\JoinupMessageDeliveryInterface;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\og\MembershipManager;
use Drupal\og\OgMembershipInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Handles notifications related to changes in group memberships.
 */
class OgMembershipSubscriber extends NotificationSubscriberBase {

  const TEMPLATE_REQUEST_MEMBERSHIP = 'og_membership_request';
  const TEMPLATE_APPROVE_MEMBERSHIP = 'og_membership_approve';
  const TEMPLATE_REJECT_MEMBERSHIP = 'og_membership_reject';
  const TEMPLATE_APPROVE_MEMBERSHIP_WITH_SUBSCRIPTION = 'og_membership_subscribed_approve';

  /**
   * The membership object.
   *
   * @var \Drupal\og\Entity\OgMembership
   */
  protected $membership;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactory $configFactory,
    AccountProxy $currentUser,
    MembershipManager $ogMembershipManager,
    WorkflowHelperInterface $workflowHelper,
    JoinupMessageDeliveryInterface $messageDelivery,
    #[Autowire(service: 'joinup_notification.entity_message_helper')]
    protected readonly EntityMessageHelper $entityMessageHelper,
  ) {
    parent::__construct($entityTypeManager, $configFactory, $currentUser, $ogMembershipManager, $workflowHelper, $messageDelivery);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[NotificationEvents::OG_MEMBERSHIP_MANAGEMENT] = [
      ['onCreate'],
      ['onUpdate'],
      ['onDelete'],
    ];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function initialize(NotificationEvent $event): void {
    /** @var \Drupal\og\Entity\OgMembership $membership */
    $this->membership = $event->getEntity();
    try {
      $this->entity = $this->membership->getGroup();
    }
    catch (\TypeError $exception) {
      // The group might have been deleted.
    }

    $this->operation = $event->getOperation();
  }

  /**
   * Sends notification when a membership is requested.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onCreate(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnCreate()) {
      return;
    }

    $template_id = self::TEMPLATE_REQUEST_MEMBERSHIP;
    $user_data_array = [
      'og_roles' => [
        'node-collection-administrator' => [
          $template_id,
        ],
        'node-collection-facilitator' => [
          $template_id,
        ],
      ],
    ];

    $user_data = $this->getUsersMessages($user_data_array);
    $this->sendUserDataMessages($user_data);
  }

  /**
   * Checks if the conditions apply for the onCreate method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnCreate(): bool {
    if ($this->operation !== 'create') {
      return FALSE;
    }

    if (!$this->membership->getGroup()) {
      return FALSE;
    }

    if ($this->membership->getState() !== OgMembershipInterface::STATE_PENDING) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sends notifications when a membership is approved.
   *
   * Notifications handled: All.
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onUpdate(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnUpdate()) {
      return;
    }

    $recipient_id = $this->membership->getOwnerId();
    if ($this->entityMessageHelper->hasCustomApprovalMessage($this->entity->id())) {
      $template = $this->entity->id() . '_approval_message';
    }
    else {
      $template = $this->membership->get('subscription_bundles')->isEmpty() ?
        self::TEMPLATE_APPROVE_MEMBERSHIP :
        self::TEMPLATE_APPROVE_MEMBERSHIP_WITH_SUBSCRIPTION;
    }
    $user_data = [
      $template => [
        $recipient_id => $recipient_id,
      ],
    ];
    $this->sendUserDataMessages($user_data);
  }

  /**
   * Checks if the conditions apply for the onUpdate method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnUpdate(): bool {
    if ($this->operation !== 'update') {
      return FALSE;
    }

    if (!$this->membership->getGroup()) {
      return FALSE;
    }

    $original_membership = $this->membership->getOriginal();
    if (empty($original_membership)) {
      return FALSE;
    }

    // Only send an email when a membership is approved.
    if ($original_membership->getState() !== OgMembershipInterface::STATE_PENDING || $this->membership->getState() !== OgMembershipInterface::STATE_ACTIVE) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Sends notifications when a membership is rejected(deleted).
   *
   * @param \Drupal\joinup_notification\Event\NotificationEvent $event
   *   The notification event.
   */
  public function onDelete(NotificationEvent $event): void {
    $this->initialize($event);
    if (!$this->appliesOnDelete()) {
      return;
    }

    $recipient_id = $this->membership->getOwnerId();
    $user_data = [
      self::TEMPLATE_REJECT_MEMBERSHIP => [
        $recipient_id => $recipient_id,
      ],
    ];
    $this->sendUserDataMessages($user_data);
  }

  /**
   * Checks if the conditions apply for the onDelete method.
   *
   * @return bool
   *   Whether the conditions apply.
   */
  protected function appliesOnDelete(): bool {
    if ($this->operation !== 'delete') {
      return FALSE;
    }

    // Avoid sending notifications if the deletion of the membership is a result
    // of deleting the group.
    if (empty($this->membership->getGroup())) {
      return FALSE;
    }

    // Do not send a membership rejection e-email if the state of the membership
    // is not pending as it is not a reject but a delete.
    if ($this->membership->getState() !== OgMembershipInterface::STATE_PENDING) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateArguments(EntityInterface $message): array {
    $arguments = parent::generateArguments($message);
    $actor_first_name = $arguments['@actor:field_user_first_name'];
    $actor_last_name = $arguments['@actor:field_user_family_name'];

    // Ensure that the full name is not empty.
    if (empty($actor_first_name) && empty(($actor_last_name))) {
      $arguments['@actor:full_name'] = 'A Interoperable Europe Portal user';
    }
    else {
      /** @var \Drupal\user\UserInterface $actor */
      $actor = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
      $arguments['@actor:full_name'] = $actor->getDisplayName();
    }

    if ($this->operation === 'create') {
      $arguments['@group:members_page:url'] = $this->getMembersUrl();
    }

    return $arguments;
  }

  /**
   * Returns the member's page of the group.
   *
   * @return string
   *   The url.
   */
  protected function getMembersUrl(): string {
    $entity_type_id = $this->entity->getEntityTypeId();
    $route_name = "entity.$entity_type_id.member_overview";
    $route_parameters = [
      $entity_type_id => $this->entity->id(),
    ];
    $url = Url::fromRoute($route_name, $route_parameters, ['absolute' => TRUE])->toString();
    return $url;
  }

}
