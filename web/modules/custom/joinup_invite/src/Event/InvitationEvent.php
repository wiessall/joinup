<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Event;

use Drupal\joinup_invite\Entity\InvitationInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Events that fire when invitations are accepted or rejected.
 */
class InvitationEvent extends Event implements InvitationEventInterface {

  /**
   * The invitation.
   *
   * @var \Drupal\joinup_invite\Entity\InvitationInterface
   */
  protected InvitationInterface $invitation;

  /**
   * The action.
   *
   * @var string
   */
  protected string $action;

  /**
   * Subscribe.
   *
   * @var bool
   */
  protected bool $subscribe = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getInvitation(): InvitationInterface {
    return $this->invitation;
  }

  /**
   * {@inheritdoc}
   */
  public function setInvitation(InvitationInterface $invitation): InvitationEventInterface {
    $this->invitation = $invitation;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAction(): string {
    return $this->action;
  }

  /**
   * {@inheritdoc}
   */
  public function setAction(string $action): InvitationEventInterface {
    $this->action = $action;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubscribe(bool $subscribe): InvitationEventInterface {
    $this->subscribe = $subscribe;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribe(): bool {
    return $this->subscribe;
  }

}
