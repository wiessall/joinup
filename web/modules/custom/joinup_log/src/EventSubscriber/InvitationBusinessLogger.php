<?php

declare(strict_types=1);

namespace Drupal\joinup_log\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\joinup_invite\Event\InvitationEventInterface;
use Drupal\joinup_invite\Event\InvitationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Invitation business logger.
 */
class InvitationBusinessLogger implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Perform events at the very end.
    $events[InvitationEvents::ACCEPT_INVITATION_EVENT] =
      ['acceptInvitation', -1];

    return $events;
  }

  /**
   * Log acceptation.
   *
   * @param \Drupal\joinup_invite\Event\InvitationEventInterface $event
   *   The event that was fired.
   */
  public function acceptInvitation(InvitationEventInterface $event): void {
    if (!$this->isGroupMembership($event)) {
      return;
    }

    $invitation = $event->getInvitation();
    $role = $invitation->get('field_invitation_og_role')->entity;

    $this->logger->info('Invitation was accepted with %role role.', [
      '%role' => $role->label(),
    ]);
  }

  /**
   * Checks if an invitation is for a group membership.
   *
   * @param \Drupal\joinup_invite\Event\InvitationEventInterface $event
   *   The invitation event.
   *
   * @return bool
   *   TRUE if the invitation is for a group membership, FALSE otherwise.
   */
  protected function isGroupMembership(InvitationEventInterface $event): bool {
    $invitation = $event->getInvitation();
    if ($invitation->bundle() !== 'group_membership') {
      return FALSE;
    }

    return TRUE;
  }

}
