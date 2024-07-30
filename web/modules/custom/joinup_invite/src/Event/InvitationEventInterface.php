<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Event;

use Drupal\joinup_invite\Entity\InvitationInterface;

/**
 * Interface for events that fire when invitations are accepted or rejected.
 */
interface InvitationEventInterface {

  /**
   * Returns the invitation for which the event takes place.
   *
   * @return \Drupal\joinup_invite\Entity\InvitationInterface
   *   The invitation.
   */
  public function getInvitation(): InvitationInterface;

  /**
   * Stores the invitation for which the event takes place.
   *
   * @param \Drupal\joinup_invite\Entity\InvitationInterface $invitation
   *   The invitation.
   *
   * @return $this
   */
  public function setInvitation(InvitationInterface $invitation): self;

  /**
   * Returns the action taken on the invitation for which the event takes place.
   *
   * @return string
   *   The action.
   */
  public function getAction(): string;

  /**
   * Stores the action taken on the invitation for which the event takes place.
   *
   * @param string $action
   *   The action.
   *
   * @return $this
   */
  public function setAction(string $action): self;

  /**
   * Sets whether to subscribe or not.
   *
   * @param bool $subscribe
   *   Whether to subscribe or not.
   *
   * @return InvitationEventInterface
   *   The updated InvitationEventInterface instance.
   */
  public function setSubscribe(bool $subscribe): InvitationEventInterface;

  /**
   * Gets the subscribe value.
   *
   * @return bool
   *   The subscribe value.
   */
  public function getSubscribe(): bool;

}
