<?php

declare(strict_types=1);

namespace Joinup\PhpUnit\EventSubscriber;

use Joinup\TaskRunner\Traits\TaskRunnerTrait;
use Joinup\TestModeManager;
use PHPUnit\Event\TestSuite\Finished;
use PHPUnit\Event\TestSuite\FinishedSubscriber;

/**
 * Listens to PHPUnit finished event.
 */
class JoinupFinishedSubscriber implements FinishedSubscriber {

  use TaskRunnerTrait;

  /**
   * {@inheritdoc}
   */
  public function notify(Finished $event): void {
    static::runCommand("drupal:settings site-clean --root={$this->getPath('web')} --sites-subdir=default");
    TestModeManager::clear();
  }

}
