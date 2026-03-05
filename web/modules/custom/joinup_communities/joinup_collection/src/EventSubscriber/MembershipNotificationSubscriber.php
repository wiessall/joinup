<?php

declare(strict_types=1);

namespace Drupal\joinup_collection\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\joinup_notification\EventSubscriber\NotificationSubscriberBase;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\og\OgMembershipInterface;

/**
 * Handles notifications related to changes in collection memberships.
 */
class MembershipNotificationSubscriber extends NotificationSubscriberBase {

  const TEMPLATE_REVOKE_MEMBERSHIP_COC = 'col_membership_block_coc';

  /**
   * The membership object.
   *
   * @var \Drupal\og\Entity\OgMembership
   */
  protected $membership;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[NotificationEvents::OG_MEMBERSHIP_MANAGEMENT] = [
      ['onUpdate'],
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
   * Sends notifications when a membership is updated.
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

    // Act on specific membership state transitions.
    $original_membership = $this->membership->getOriginal();
    match ([$original_membership->getState(), $this->membership->getState()]) {
      [OgMembershipInterface::STATE_ACTIVE, OgMembershipInterface::STATE_BLOCKED] => $this->onUpdateMembershipBlocked(),
      default => NULL,
    };
  }

  /**
   * Sends notifications when a membership is blocked.
   */
  protected function onUpdateMembershipBlocked(): void {
    if (!$this->entity instanceof CollectionInterface || !$this->entity->hasCodeOfConductToAccept()) {
      return;
    }

    $recipient_id = $this->membership->getOwnerId();
    $user_data = [
      self::TEMPLATE_REVOKE_MEMBERSHIP_COC => [
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

    if (empty($this->membership->getOriginal())) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateArguments(EntityInterface $entity): array {
    $arguments = parent::generateArguments($entity);
    \assert($this->entity instanceof CollectionInterface);

    if ($this->entity->hasCodeOfConductToAccept()) {
      $arguments['@entity:code_of_conduct:url'] = $this->entity->getCodeOfConduct()->toUrl()->setAbsolute()->toString();
    }

    if ($this->entity->getValidatedContactInformationEmails()) {
      $arguments['@entity:contact:url'] = Url::fromRoute('entity.node.contact', ['node' => $this->entity->id()])->setAbsolute()->toString();
    }

    return $arguments;
  }

}
