<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Update;

use Drupal\Core\Session\AccountSwitcherInterface;

/**
 * Runs an update process as the backend moderator account.
 */
readonly class BackendModeratorRunner {

  public function __construct(
    private BackendModerator $backendModerator,
    private AccountSwitcherInterface $accountSwitcher,
  ) {}

  /**
   * Switches to the backend moderator, runs the process, then switches back.
   *
   * @param \Drupal\joinup_core\Update\UpdateProcessInterface $process
   *   The process to run.
   * @param array $sandbox
   *   The sandbox array passed by the deploy hook.
   *
   * @throws \Exception
   *   If the backend moderator account cannot be found.
   */
  public function run(UpdateProcessInterface $process, array &$sandbox): void {
    $this->accountSwitcher->switchTo($this->backendModerator->getServiceAccount());
    try {
      $process->process($sandbox);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

}
