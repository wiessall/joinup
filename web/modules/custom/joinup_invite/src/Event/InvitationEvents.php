<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Event;

/**
 * Events for the Joinup Invite module.
 */
final class InvitationEvents {

  /**
   * An event that fires when an invitation is accepted.
   *
   * @Event
   */
  const string ACCEPT_INVITATION_EVENT = 'joinup_invite.accept';

  /**
   * An event that fires when an invitation is accepted.
   *
   * @Event
   */
  const string REJECT_INVITATION_EVENT = 'joinup_invite.reject';

  /**
   * An event that fires when an invitation is accepted.
   *
   * @Event
   */
  const string NOT_PENDING_EVENT = 'joinup_invite.not_pending';

}
