<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_invite\Entity\InvitationInterface;
use Drupal\joinup_invite\Event\InvitationEventInterface;
use Drupal\joinup_invite\Event\InvitationEvents;
use Drupal\og\MembershipManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber handling invitations to groups.
 */
class InvitationSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MembershipManagerInterface $membershipManager,
    protected MessengerInterface $messenger,
    protected LoggerChannelInterface $logger,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[InvitationEvents::NOT_PENDING_EVENT] = ['notPendingInvitation'];
    $events[InvitationEvents::ACCEPT_INVITATION_EVENT] = ['acceptInvitation'];
    $events[InvitationEvents::REJECT_INVITATION_EVENT] = ['rejectInvitation'];

    return $events;
  }

  /**
   * Invitation is already accepted or rejected.
   *
   * @param \Drupal\joinup_invite\Event\InvitationEventInterface $event
   *   The event that was fired.
   */
  public function notPendingInvitation(InvitationEventInterface $event): void {
    $invitation = $event->getInvitation();

    // Ignore invitations to other content entities.
    if ($invitation->bundle() !== 'group_membership') {
      return;
    }
    $this->messenger->addStatus($this->t('You have already %action the invitation.', [
      '%action' => $invitation->getStatus(),
    ]));
  }

  /**
   * Accepts invitations to groups.
   *
   * @param \Drupal\joinup_invite\Event\InvitationEventInterface $event
   *   The event that was fired.
   */
  public function acceptInvitation(InvitationEventInterface $event): void {
    $invitation = $event->getInvitation();

    // Ignore invitations to other content entities.
    if ($invitation->bundle() !== 'group_membership') {
      return;
    }

    $membership = $this->membershipManager->getMembership($invitation->getEntity(), $invitation->getRecipientId());
    if (empty($membership)) {
      $membership = $this->membershipManager->createMembership($invitation->getEntity(), $invitation->getRecipient());
    }

    // Add the role appointed to the invitation, if one has.
    $role = $invitation->field_invitation_og_role->entity;
    if (empty($role)) {
      // This might happen if the role is deleted in the meantime.
      throw new \RuntimeException(sprintf('Role with ID "%s" was not found in the system.', $invitation->field_invitation_og_role->target_id));
    }

    $membership->addRole($role);
    $membership->save();

    $invitation->setStatus(InvitationInterface::STATUS_ACCEPTED)->save();
    $this->messenger->addMessage($this->t('You have been promoted to %role.', [
      '%role' => strtolower($role->label()),
    ]));
    $this->logger->info('Invitation was accepted with @role role.', [
      '@role' => strtolower($role->label()),
      'type' => 'joinup:approve_the_pending_membership',
      'actor' => $this->currentUser->id(),
    ]);
  }

  /**
   * Rejects invitations to the group.
   *
   * @param \Drupal\joinup_invite\Event\InvitationEventInterface $event
   *   The event that was fired.
   */
  public function rejectInvitation(InvitationEventInterface $event): void {
    $invitation = $event->getInvitation();
    if ($invitation->bundle() !== 'group_membership') {
      return;
    }

    $invitation->setStatus(InvitationInterface::STATUS_REJECTED)->save();
    $this->messenger->addMessage($this->t('You have rejected the invitation.'));
  }

}
