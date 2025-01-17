<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Event;

use Drupal\joinup_user\Entity\JoinupUserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class representing a 'joinup_user.cancel' event.
 */
class JoinupUserCancelEvent extends Event {

  public function __construct(
    protected readonly JoinupUserInterface $account,
  ) {}

  /**
   * Returns the account being cancelled.
   *
   * @return \Drupal\joinup_user\Entity\JoinupUserInterface
   *   The user account entity.
   */
  public function getAccount(): JoinupUserInterface {
    return $this->account;
  }

}
